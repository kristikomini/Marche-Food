<?php

namespace App\Http\Controllers;

use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\MateriaPrima;
use App\Models\AcquistoRiga;
use App\Models\LottoSemilavorato;
use App\Models\LottoImballaggioPrimario;
use App\Models\LottoDetergente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProduzioneController extends Controller
{
    public function index(Request $request)
    {
        $query = Produzione::with('scheda.prodotto');

        if ($search = $request->input('search')) {
            $query->where('lotto_produzione', 'ilike', "%{$search}%");
        }

        if ($da = $request->input('da')) {
            $query->whereDate('data_produzione', '>=', $da);
        }

        if ($a = $request->input('a')) {
            $query->whereDate('data_produzione', '<=', $a);
        }

        $produzioni = $query->orderByDesc('data_produzione')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('Produzioni/Index', [
            'produzioni' => $produzioni,
            'filters'    => $request->only(['search', 'da', 'a']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Produzioni/Form', [
            'produzione'         => null,
            'schede'             => $this->schedeAttive(),
            'materie'            => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'lotti_disponibili'  => $this->lottiDisponibiliForForm(),
            'lotti_imballaggi'   => $this->lottiImballaggiForForm(),
            'lotti_detergenti'   => $this->lottiDetergentiForForm(),
            'lotti_gas'          => $this->lottiGasForForm(),
            'campioni'           => config('haccp.metal_detector_campioni', []),
            'lotto_semilavorato' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);
        $this->validateRecipeIngredients($data);

        DB::transaction(function () use ($data) {
            // Lock acquisto_righe rows and re-validate balance inside the transaction
            // to prevent two concurrent submissions from over-drawing the same lot.
            $this->lockAndCheckBalance($data['materie_prime'] ?? []);

            $produzione = Produzione::create([
                'scheda_id'            => $data['scheda_id'],
                'lotto_produzione'     => $data['lotto_produzione'],
                'data_produzione'      => $data['data_produzione'],
                'quantita_prodotta_kg' => $data['quantita_prodotta_kg'] ?? null,
                'operatore'            => $data['operatore'] ?? null,
                'note'                 => $data['note'] ?? null,
            ]);

            $this->syncMateriePrime($produzione, $data['materie_prime'] ?? []);
            $this->syncImballaggi($produzione, $data['imballaggi'] ?? []);
            $this->syncDetergenti($produzione, $data['detergenti'] ?? []);
            $this->syncConfezioni($produzione, $data['confezioni'] ?? []);
            $this->syncGas($produzione, $data['gas'] ?? []);
            $this->syncCiclo($produzione, $data['ciclo'] ?? []);
            $this->syncMetalDetector($produzione, $data['metal_detector'] ?? null);
        });

        return redirect()->route('produzioni.index')->with('success', 'Produzione registrata con successo.');
    }

    public function edit(Produzione $produzione)
    {
        $produzione->load([
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'materiePrime.semilavorato',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
            'confezioni.variante',
            'gas.lottoGas.fornitore',
            'ciclo.flusso',
            'metalDetector',
            'lottoSemilavorato',
        ]);

        return Inertia::render('Produzioni/Form', [
            'produzione'          => $produzione,
            'schede'              => $this->schedeAttive(),
            'materie'             => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'lotti_disponibili'   => $this->lottiDisponibiliForForm($produzione->id),
            'lotti_imballaggi'    => $this->lottiImballaggiForForm(),
            'lotti_detergenti'    => $this->lottiDetergentiForForm(),
            'lotti_gas'           => $this->lottiGasForForm(),
            'campioni'            => config('haccp.metal_detector_campioni', []),
            'lotto_semilavorato'  => $produzione->lottoSemilavorato,
        ]);
    }

    public function update(Request $request, Produzione $produzione)
    {
        // Optimistic locking: reject if the record changed since it was loaded.
        $this->assertNotStale($produzione, $request);

        $data = $this->validateRequest($request, $produzione->id);

        // GAP-D3: cross-check submitted ingredients against the scheda recipe
        $this->validateRecipeIngredients($data);

        DB::transaction(function () use ($produzione, $data) {
            // Exclude this production's own current consumption from the balance check
            $this->lockAndCheckBalance($data['materie_prime'] ?? [], $produzione->id);

            $produzione->update([
                'scheda_id'            => $data['scheda_id'],
                'lotto_produzione'     => $data['lotto_produzione'],
                'data_produzione'      => $data['data_produzione'],
                'quantita_prodotta_kg' => $data['quantita_prodotta_kg'] ?? null,
                'operatore'            => $data['operatore'] ?? null,
                'note'                 => $data['note'] ?? null,
            ]);

            $produzione->materiePrime()->delete();
            $this->syncMateriePrime($produzione, $data['materie_prime'] ?? []);

            $produzione->imballaggiPrimari()->delete();
            $this->syncImballaggi($produzione, $data['imballaggi'] ?? []);

            $produzione->detergenti()->delete();
            $this->syncDetergenti($produzione, $data['detergenti'] ?? []);

            $produzione->confezioni()->delete();
            $this->syncConfezioni($produzione, $data['confezioni'] ?? []);

            $produzione->gas()->delete();
            $this->syncGas($produzione, $data['gas'] ?? []);

            $produzione->ciclo()->delete();
            $this->syncCiclo($produzione, $data['ciclo'] ?? []);

            $this->syncMetalDetector($produzione, $data['metal_detector'] ?? null);
        });

        return redirect()->route('produzioni.index')->with('success', 'Produzione aggiornata.');
    }

    public function destroy(Produzione $produzione)
    {
        // Refuse to trash a production whose semi-finished lot is consumed by an
        // active downstream production, or whose finished lot has been sold.
        $semiId = $produzione->lottoSemilavorato()->value('id');
        $semiConsumed = $semiId && \App\Models\ProduzioneMateriaPrima::where('semilavorato_id', $semiId)
            ->whereHas('produzione')->exists();

        $sold = \App\Models\VenditaRiga::where('produzione_id', $produzione->id)
            ->whereHas('vendita')->exists();

        if ($semiConsumed || $sold) {
            return back()->with('error', 'Impossibile eliminare: il semilavorato o il lotto di questa produzione è utilizzato in produzioni o vendite attive.');
        }

        $produzione->delete();

        return redirect()->route('produzioni.index')->with('success', 'Produzione spostata nel cestino.');
    }

    private function syncMateriePrime(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            $produzione->materiePrime()->create([
                'acquisto_riga_id' => ($r['source_type'] === 'acquisto') ? $r['acquisto_riga_id'] : null,
                'semilavorato_id'  => ($r['source_type'] === 'interno')  ? $r['semilavorato_id']  : null,
                'materia_prima_id' => $r['materia_prima_id'],
                'quantita_kg'      => $r['quantita_kg'],
            ]);
        }
    }

    private function syncImballaggi(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            $produzione->imballaggiPrimari()->create([
                'lotto_imballaggio_id' => $r['lotto_imballaggio_id'],
                'quantita_usata'       => $r['quantita_usata'] ?? null,
                'note'                 => $r['note'] ?? null,
            ]);
        }
    }

    private function syncDetergenti(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            $produzione->detergenti()->create([
                'lotto_detergente_id' => $r['lotto_detergente_id'],
                'quantita_usata'      => $r['quantita_usata'] ?? null,
                'note'                => $r['note'] ?? null,
            ]);
        }
    }

    private function syncConfezioni(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            if (empty($r['prodotto_variante_id'])) {
                continue;
            }
            $produzione->confezioni()->create([
                'prodotto_variante_id' => $r['prodotto_variante_id'],
                'n_confezioni'         => $r['n_confezioni'] ?? null,
            ]);
        }
    }

    private function syncGas(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            if (empty($r['lotto_gas_id'])) {
                continue;
            }
            $produzione->gas()->create([
                'lotto_gas_id'   => $r['lotto_gas_id'],
                'quantita_usata' => $r['quantita_usata'] ?? null,
                'note'           => $r['note'] ?? null,
            ]);
        }
    }

    private function syncCiclo(Produzione $produzione, array $righe): void
    {
        foreach (array_values($righe) as $i => $r) {
            $produzione->ciclo()->create([
                'flusso_id'       => $r['flusso_id'] ?? null,
                'nome'            => $r['nome'] ?? null,
                'registrazione_1' => $r['registrazione_1'] ?? null,
                'registrazione_2' => $r['registrazione_2'] ?? null,
                'controllo'       => !empty($r['controllo']),
                'ordine'          => $i + 1,
            ]);
        }
    }

    private function syncMetalDetector(Produzione $produzione, ?array $md): void
    {
        $produzione->metalDetector()->delete();

        if (!$md) {
            return;
        }

        $hasData = collect(['inizio_conf', 'fine_conf', 'campione_1', 'campione_2', 'campione_3', 'note'])
            ->contains(fn ($k) => !empty($md[$k] ?? null));

        if (!$hasData) {
            return;
        }

        $produzione->metalDetector()->create([
            'inizio_conf' => $md['inizio_conf'] ?? null,
            'fine_conf'   => $md['fine_conf'] ?? null,
            'campione_1'  => $md['campione_1'] ?? null,
            'campione_2'  => $md['campione_2'] ?? null,
            'campione_3'  => $md['campione_3'] ?? null,
            'note'        => $md['note'] ?? null,
        ]);
    }

    private function schedeAttive()
    {
        return SchedaProduzione::with(['prodotto.varianti', 'flussi.flusso'])
            ->where('attiva', true)
            ->orderBy('modello')
            ->get(['id', 'prodotto_id', 'modello', 'revisione'])
            ->map(fn ($s) => [
                'id'        => $s->id,
                'prodotto_id' => $s->prodotto_id,
                'modello'   => $s->modello,
                'revisione' => $s->revisione,
                'prodotto'  => $s->prodotto ? [
                    'id'   => $s->prodotto->id,
                    'nome' => $s->prodotto->nome,
                    'varianti' => $s->prodotto->varianti->map(fn ($v) => [
                        'id' => $v->id,
                        'codice_prodotto' => $v->codice_prodotto,
                        'pezzatura_label' => $v->pezzatura_label,
                    ])->values(),
                ] : null,
                // Passi del ciclo di lavoro proposti dalla scheda (per il pre-fill)
                'ciclo' => $s->flussi->map(fn ($f) => [
                    'flusso_id' => $f->flusso_id,
                    'numero'    => $f->flusso?->numero,
                    'nome'      => $f->flusso?->nome,
                    'controllo' => $f->flusso?->controllo,
                ])->values(),
            ]);
    }

    private function lottiGasForForm()
    {
        return \App\Models\LottoGas::with('fornitore:id,ragione_sociale')
            ->whereNull('data_out')
            ->orderByDesc('data_in')
            ->get(['id', 'fornitore_id', 'componente', 'lotto', 'numero_ddt', 'quantita', 'um', 'data_in']);
    }

    private function lottiDisponibiliForForm(?int $excludeProduzioneId = null): array
    {
        // GAP-D2 (extended): balance for purchased lots = received - consumed in productions - sold directly
        $consumedPurchased = DB::table('produzioni_materie_prime')
            ->join('produzioni', 'produzioni.id', '=', 'produzioni_materie_prime.produzione_id')
            ->whereNull('produzioni.deleted_at')
            ->whereNotNull('acquisto_riga_id')
            ->when($excludeProduzioneId, fn($q) => $q->where('produzioni_materie_prime.produzione_id', '!=', $excludeProduzioneId))
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(produzioni_materie_prime.quantita_kg) as s'), 'acquisto_riga_id');

        $soldDirectly = DB::table('vendite_righe')
            ->join('vendite', 'vendite.id', '=', 'vendite_righe.vendita_id')
            ->whereNull('vendite.deleted_at')
            ->whereNotNull('acquisto_riga_id')
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(vendite_righe.quantita_kg) as s'), 'acquisto_riga_id');

        $purchasedLots = AcquistoRiga::whereHas('acquisto')
            ->with(['acquisto' => fn($q) => $q->with('fornitore:id,ragione_sociale,codice')])
            ->orderByDesc('data_in')
            ->get(['id', 'acquisto_id', 'nome_prodotto', 'lotto', 'lotto_esterno', 'quantita_kg', 'scadenza', 'data_in'])
            ->map(function ($riga) use ($consumedPurchased, $soldDirectly) {
                $riga->balance_kg  = round(
                    (float) $riga->quantita_kg
                    - (float) ($consumedPurchased[$riga->id] ?? 0)
                    - (float) ($soldDirectly[$riga->id] ?? 0),
                    3
                );
                $riga->source_type = 'acquisto';
                return $riga;
            });

        // Balance for internal lots = produced qty - consumed in downstream productions
        $consumedInternal = DB::table('produzioni_materie_prime')
            ->join('produzioni', 'produzioni.id', '=', 'produzioni_materie_prime.produzione_id')
            ->whereNull('produzioni.deleted_at')
            ->whereNotNull('semilavorato_id')
            ->when($excludeProduzioneId, fn($q) => $q->where('produzioni_materie_prime.produzione_id', '!=', $excludeProduzioneId))
            ->groupBy('semilavorato_id')
            ->pluck(DB::raw('SUM(produzioni_materie_prime.quantita_kg) as s'), 'semilavorato_id');

        $internalLots = LottoSemilavorato::whereHas('produzione')
            ->whereNull('data_out')
            ->orderByDesc('data_produzione')
            ->get(['id', 'produzione_id', 'lotto', 'nome_prodotto', 'quantita_kg', 'data_produzione'])
            ->map(function ($semi) use ($consumedInternal) {
                $semi->balance_kg  = round((float) $semi->quantita_kg - (float) ($consumedInternal[$semi->id] ?? 0), 3);
                $semi->source_type = 'interno';
                return $semi;
            });

        return $purchasedLots->concat($internalLots)->values()->all();
    }

    private function lottiImballaggiForForm()
    {
        return LottoImballaggioPrimario::with('fornitore:id,ragione_sociale')
            ->whereNull('data_out')
            ->orderByDesc('data_in')
            ->get(['id', 'fornitore_id', 'componente', 'lotto', 'numero_ddt', 'quantita', 'um', 'data_in']);
    }

    private function lottiDetergentiForForm()
    {
        return LottoDetergente::with('fornitore:id,ragione_sociale')
            ->whereNull('data_out')
            ->orderByDesc('data_in')
            ->get(['id', 'fornitore_id', 'componente', 'lotto', 'numero_ddt', 'quantita', 'um', 'data_in']);
    }

    private function lockAndCheckBalance(array $materiePrime, ?int $excludeProduzioneId = null): void
    {
        if (empty($materiePrime)) {
            return;
        }

        $purchaseIds = collect($materiePrime)
            ->where('source_type', 'acquisto')->pluck('acquisto_riga_id')->unique()->filter()->all();
        $internalIds = collect($materiePrime)
            ->where('source_type', 'interno')->pluck('semilavorato_id')->unique()->filter()->all();

        // Lock both tables in deterministic order to prevent deadlocks
        $righe = $purchaseIds
            ? AcquistoRiga::lockForUpdate()->whereIn('id', $purchaseIds)->get()->keyBy('id')
            : collect();
        $semis = $internalIds
            ? LottoSemilavorato::lockForUpdate()->whereIn('id', $internalIds)->get()->keyBy('id')
            : collect();

        // Fresh consumed totals inside the transaction
        $consumedPurchased = $purchaseIds ? DB::table('produzioni_materie_prime')
            ->join('produzioni', 'produzioni.id', '=', 'produzioni_materie_prime.produzione_id')
            ->whereNull('produzioni.deleted_at')
            ->whereNotNull('acquisto_riga_id')->whereIn('acquisto_riga_id', $purchaseIds)
            ->when($excludeProduzioneId, fn($q) => $q->where('produzioni_materie_prime.produzione_id', '!=', $excludeProduzioneId))
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(produzioni_materie_prime.quantita_kg) as s'), 'acquisto_riga_id') : collect();

        $soldDirectly = $purchaseIds ? DB::table('vendite_righe')
            ->join('vendite', 'vendite.id', '=', 'vendite_righe.vendita_id')
            ->whereNull('vendite.deleted_at')
            ->whereNotNull('acquisto_riga_id')->whereIn('acquisto_riga_id', $purchaseIds)
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(vendite_righe.quantita_kg) as s'), 'acquisto_riga_id') : collect();

        $consumedInternal = $internalIds ? DB::table('produzioni_materie_prime')
            ->join('produzioni', 'produzioni.id', '=', 'produzioni_materie_prime.produzione_id')
            ->whereNull('produzioni.deleted_at')
            ->whereNotNull('semilavorato_id')->whereIn('semilavorato_id', $internalIds)
            ->when($excludeProduzioneId, fn($q) => $q->where('produzioni_materie_prime.produzione_id', '!=', $excludeProduzioneId))
            ->groupBy('semilavorato_id')
            ->pluck(DB::raw('SUM(produzioni_materie_prime.quantita_kg) as s'), 'semilavorato_id') : collect();

        $errors = [];

        foreach ($materiePrime as $mp) {
            $required = (float) $mp['quantita_kg'];

            if (($mp['source_type'] ?? null) === 'interno') {
                $semi = $semis[$mp['semilavorato_id']] ?? null;
                if (!$semi) continue;
                $balance = round((float) $semi->quantita_kg - (float) ($consumedInternal[$semi->id] ?? 0), 3);
                $label   = $semi->lotto;
            } else {
                $riga = $righe[$mp['acquisto_riga_id']] ?? null;
                if (!$riga) continue;
                $balance = round(
                    (float) $riga->quantita_kg
                    - (float) ($consumedPurchased[$riga->id] ?? 0)
                    - (float) ($soldDirectly[$riga->id] ?? 0),
                    3
                );
                $label = $riga->lotto ?: ($riga->lotto_esterno ?: "ID {$riga->id}");
            }

            if ($required > $balance + 0.001) {
                $errors[] = "Lotto «{$label}»: richiesti {$required} kg, disponibili {$balance} kg.";
            }
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'materie_prime' => implode(' | ', $errors),
            ]);
        }
    }

    private function validateRecipeIngredients(array $data): void
    {
        $scheda = SchedaProduzione::with(['ricette', 'ricetteMarinature'])->find($data['scheda_id']);

        if (!$scheda) {
            return;
        }

        $recipeIngredients = $scheda->ricette->pluck('materia_prima_id')
            ->merge($scheda->ricetteMarinature->pluck('materia_prima_id'))
            ->unique()
            ->all();

        // Only validate if the scheda has a defined recipe
        if (empty($recipeIngredients)) {
            return;
        }

        $submittedIngredients = collect($data['materie_prime'] ?? [])
            ->pluck('materia_prima_id')
            ->filter()
            ->all();

        $invalid = array_diff($submittedIngredients, $recipeIngredients);
        if (!empty($invalid)) {
            $names = MateriaPrima::whereIn('id', $invalid)->pluck('nome')->implode(', ');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'materie_prime' => "Ingredienti non presenti nella ricetta della scheda: {$names}.",
            ]);
        }
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $validator = Validator::make($request->all(), [
            'scheda_id'            => ['required', 'exists:schede_produzione,id'],
            'lotto_produzione'     => ['required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('produzioni', 'lotto_produzione')->ignore($ignoreId)],
            'data_produzione'      => ['required', 'date'],
            'quantita_prodotta_kg' => ['nullable', 'numeric', 'min:0'],
            'operatore'            => ['nullable', 'string', 'max:100'],
            'note'                 => ['nullable', 'string'],
            'materie_prime'        => ['array'],
            'materie_prime.*.materia_prima_id' => ['required', 'exists:materie_prime,id'],
            'materie_prime.*.source_type'      => ['required', 'in:acquisto,interno'],
            'materie_prime.*.acquisto_riga_id' => ['nullable', 'integer', 'exists:acquisti_righe,id'],
            'materie_prime.*.semilavorato_id'  => ['nullable', 'integer', 'exists:lotti_semilavorati,id'],
            'materie_prime.*.quantita_kg'      => ['required', 'numeric', 'min:0.001'],
            'imballaggi'           => ['array'],
            'imballaggi.*.lotto_imballaggio_id' => ['required', 'exists:lotti_imballaggi_primari,id'],
            'imballaggi.*.quantita_usata'       => ['nullable', 'numeric', 'min:0'],
            'imballaggi.*.note'                 => ['nullable', 'string'],
            'detergenti'           => ['array'],
            'detergenti.*.lotto_detergente_id'  => ['required', 'exists:lotti_detergenti,id'],
            'detergenti.*.quantita_usata'       => ['nullable', 'numeric', 'min:0'],
            'detergenti.*.note'                 => ['nullable', 'string'],
            'confezioni'           => ['array'],
            'confezioni.*.prodotto_variante_id' => ['required', 'exists:prodotto_varianti,id'],
            'confezioni.*.n_confezioni'         => ['nullable', 'integer', 'min:0'],
            'gas'                  => ['array'],
            'gas.*.lotto_gas_id'   => ['required', 'exists:lotti_gas,id'],
            'gas.*.quantita_usata' => ['nullable', 'numeric', 'min:0'],
            'gas.*.note'           => ['nullable', 'string'],
            'ciclo'                => ['array'],
            'ciclo.*.flusso_id'        => ['nullable', 'exists:flussi_produzione,id'],
            'ciclo.*.nome'             => ['nullable', 'string', 'max:150'],
            'ciclo.*.registrazione_1'  => ['nullable', 'string', 'max:200'],
            'ciclo.*.registrazione_2'  => ['nullable', 'string', 'max:200'],
            'ciclo.*.controllo'        => ['boolean'],
            'metal_detector'              => ['nullable', 'array'],
            'metal_detector.inizio_conf'  => ['nullable', 'string', 'max:20'],
            'metal_detector.fine_conf'    => ['nullable', 'string', 'max:20'],
            'metal_detector.campione_1'   => ['nullable', 'string', 'in:OK,KO'],
            'metal_detector.campione_2'   => ['nullable', 'string', 'in:OK,KO'],
            'metal_detector.campione_3'   => ['nullable', 'string', 'in:OK,KO'],
            'metal_detector.note'         => ['nullable', 'string'],
        ]);

        // XOR: exactly one of acquisto_riga_id / semilavorato_id must be present per row
        $validator->after(function ($v) use ($request) {
            foreach ($request->input('materie_prime', []) as $i => $mp) {
                $hasA = !empty($mp['acquisto_riga_id']);
                $hasS = !empty($mp['semilavorato_id']);
                if (!($hasA xor $hasS)) {
                    $v->errors()->add(
                        "materie_prime.{$i}",
                        'Ogni ingrediente deve avere esattamente una fonte (lotto acquisto oppure semilavorato, non entrambi né nessuno).'
                    );
                }
            }
        });

        return $validator->validate();
    }

    public function storeSemilavorato(Request $request, Produzione $produzione)
    {
        if ($produzione->lottoSemilavorato()->exists()) {
            return back()->withErrors([
                'lotto_semilavorato' => 'Questa produzione ha già un lotto semilavorato registrato.',
            ]);
        }

        $data = $request->validate([
            'lotto'         => ['required', 'string', 'max:100', 'unique:lotti_semilavorati,lotto'],
            'nome_prodotto' => ['required', 'string', 'max:200'],
            'quantita_kg'   => ['required', 'numeric', 'min:0.001'],
            'note'          => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($produzione, $data) {
            // Lock the parent production to prevent concurrent double-registration
            Produzione::lockForUpdate()->find($produzione->id);

            // Re-check inside the transaction after acquiring the lock
            if ($produzione->lottoSemilavorato()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lotto_semilavorato' => 'Questa produzione ha già un lotto semilavorato registrato.',
                ]);
            }

            $produzione->lottoSemilavorato()->create([
                'lotto'           => $data['lotto'],
                'nome_prodotto'   => $data['nome_prodotto'],
                'quantita_kg'     => $data['quantita_kg'],
                'data_produzione' => $produzione->data_produzione,
                'note'            => $data['note'] ?? null,
            ]);
        });

        return redirect()->route('produzioni.edit', $produzione)
            ->with('success', 'Lotto semilavorato registrato e disponibile per produzioni future.');
    }

    public function export()
    {
        $produzioni = Produzione::with(['scheda.prodotto'])
            ->orderByDesc('data_produzione')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="produzioni_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($produzioni) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data Produzione', 'Lotto', 'Prodotto', 'Scheda', 'Q.tà (kg)', 'Operatore'], ';');
            foreach ($produzioni as $p) {
                fputcsv($handle, [
                    $p->data_produzione,
                    $p->lotto_produzione,
                    $p->scheda?->prodotto?->nome,
                    $p->scheda ? ($p->scheda->modello . '.v' . $p->scheda->revisione) : null,
                    $p->quantita_prodotta_kg,
                    $p->operatore,
                ], ';');
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'produzioni_' . now()->format('Ymd_His') . '.csv', $headers);
    }

    public function print(Produzione $produzione)
    {
        $produzione->load([
            'scheda.prodotto.varianti',
            'scheda.flussi.flusso',
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
            'confezioni.variante',
            'gas.lottoGas.fornitore',
            'ciclo.flusso',
            'metalDetector',
        ]);

        return Inertia::render('Produzioni/Print', [
            'produzione' => $produzione,
            'campioni'   => config('haccp.metal_detector_campioni', []),
        ]);
    }
}
