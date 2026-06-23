<?php

namespace App\Http\Controllers;

use App\Models\Produzione;
use App\Models\SchedaProduzione;
use App\Models\MateriaPrima;
use App\Models\AcquistoRiga;
use App\Models\LottoImballaggioPrimario;
use App\Models\LottoDetergente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'acquisti_righe'     => $this->acquistiRigheForForm(),
            'lotti_imballaggi'   => $this->lottiImballaggiForForm(),
            'lotti_detergenti'   => $this->lottiDetergentiForForm(),
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
        });

        return redirect()->route('produzioni.index')->with('success', 'Produzione registrata con successo.');
    }

    public function edit(Produzione $produzione)
    {
        $produzione->load([
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
        ]);

        return Inertia::render('Produzioni/Form', [
            'produzione'         => $produzione,
            'schede'             => $this->schedeAttive(),
            'materie'            => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'acquisti_righe'     => $this->acquistiRigheForForm($produzione->id),
            'lotti_imballaggi'   => $this->lottiImballaggiForForm(),
            'lotti_detergenti'   => $this->lottiDetergentiForForm(),
        ]);
    }

    public function update(Request $request, Produzione $produzione)
    {
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
        });

        return redirect()->route('produzioni.index')->with('success', 'Produzione aggiornata.');
    }

    public function destroy(Produzione $produzione)
    {
        $produzione->delete();

        return redirect()->route('produzioni.index')->with('success', 'Produzione eliminata.');
    }

    private function syncMateriePrime(Produzione $produzione, array $righe): void
    {
        foreach ($righe as $r) {
            $produzione->materiePrime()->create([
                'acquisto_riga_id' => $r['acquisto_riga_id'],
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

    private function schedeAttive()
    {
        return SchedaProduzione::with('prodotto')
            ->where('attiva', true)
            ->orderBy('modello')
            ->get(['id', 'prodotto_id', 'modello', 'revisione']);
    }

    private function acquistiRigheForForm(?int $excludeProduzioneId = null)
    {
        // GAP-D2: compute remaining balance (received - already consumed by other productions)
        $consumed = DB::table('produzioni_materie_prime')
            ->when($excludeProduzioneId, fn($q) => $q->where('produzione_id', '!=', $excludeProduzioneId))
            ->groupBy('acquisto_riga_id')
            ->select('acquisto_riga_id', DB::raw('SUM(quantita_kg) as consumed'))
            ->pluck('consumed', 'acquisto_riga_id');

        return AcquistoRiga::with(['acquisto' => fn($q) => $q->with('fornitore:id,ragione_sociale,codice')])
            ->orderByDesc('data_in')
            ->get(['id', 'acquisto_id', 'nome_prodotto', 'lotto', 'lotto_esterno', 'quantita_kg', 'scadenza', 'data_in'])
            ->map(function ($riga) use ($consumed) {
                $riga->balance_kg = round((float) $riga->quantita_kg - (float) ($consumed[$riga->id] ?? 0), 3);
                return $riga;
            });
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

        $righeIds = collect($materiePrime)->pluck('acquisto_riga_id')->unique()->filter()->all();

        // Row-level lock: any concurrent transaction touching the same rows must wait
        $righe = AcquistoRiga::lockForUpdate()->whereIn('id', $righeIds)->get()->keyBy('id');

        // Fresh consumed totals, excluding the current production when editing
        $consumed = DB::table('produzioni_materie_prime')
            ->when($excludeProduzioneId, fn ($q) => $q->where('produzione_id', '!=', $excludeProduzioneId))
            ->whereIn('acquisto_riga_id', $righeIds)
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(quantita_kg)'), 'acquisto_riga_id');

        $errors = [];
        foreach ($materiePrime as $mp) {
            $riga = $righe[$mp['acquisto_riga_id']] ?? null;
            if (!$riga) {
                continue;
            }
            $balance  = round((float) $riga->quantita_kg - (float) ($consumed[$riga->id] ?? 0), 3);
            $required = (float) $mp['quantita_kg'];
            if ($required > $balance + 0.001) {
                $label    = $riga->lotto ?: ($riga->lotto_esterno ?: "ID {$riga->id}");
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
            abort(422, "Ingredienti non presenti nella ricetta della scheda: {$names}.");
        }
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'scheda_id'            => ['required', 'exists:schede_produzione,id'],
            'lotto_produzione'     => ['required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('produzioni', 'lotto_produzione')->ignore($ignoreId)],
            'data_produzione'      => ['required', 'date'],
            'quantita_prodotta_kg' => ['nullable', 'numeric', 'min:0'],
            'operatore'            => ['nullable', 'string', 'max:100'],
            'note'                 => ['nullable', 'string'],
            'materie_prime'        => ['array'],
            'materie_prime.*.materia_prima_id' => ['required', 'exists:materie_prime,id'],
            'materie_prime.*.acquisto_riga_id' => ['required', 'exists:acquisti_righe,id'],
            'materie_prime.*.quantita_kg'      => ['required', 'numeric', 'min:0.001'],
            'imballaggi'           => ['array'],
            'imballaggi.*.lotto_imballaggio_id' => ['required', 'exists:lotti_imballaggi_primari,id'],
            'imballaggi.*.quantita_usata'       => ['nullable', 'numeric', 'min:0'],
            'imballaggi.*.note'                 => ['nullable', 'string'],
            'detergenti'           => ['array'],
            'detergenti.*.lotto_detergente_id'  => ['required', 'exists:lotti_detergenti,id'],
            'detergenti.*.quantita_usata'       => ['nullable', 'numeric', 'min:0'],
            'detergenti.*.note'                 => ['nullable', 'string'],
        ]);
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
            'scheda.prodotto',
            'scheda.flussi.flusso',
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
        ]);

        return Inertia::render('Produzioni/Print', ['produzione' => $produzione]);
    }
}
