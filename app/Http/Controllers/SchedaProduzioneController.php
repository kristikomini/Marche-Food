<?php

namespace App\Http\Controllers;

use App\Models\SchedaProduzione;
use App\Models\Prodotto;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\FlussoProduzione;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchedaProduzioneController extends Controller
{
    public function index(Request $request)
    {
        $query = SchedaProduzione::with('prodotto.varianti');

        if ($search = $request->input('search')) {
            $query->whereHas('prodotto', fn($q) => $q->where('nome', 'ilike', "%{$search}%"))
                  ->orWhere('modello', 'ilike', "%{$search}%");
        }

        if ($request->input('solo_attive')) {
            $query->where('attiva', true);
        }

        $schede = $query->orderBy('modello')->orderByDesc('revisione')
            ->paginate(25)->withQueryString();

        return Inertia::render('Schede/Index', [
            'schede'  => $schede,
            'filters' => $request->only(['search', 'solo_attive']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Schede/Form', array_merge(['scheda' => null], $this->formOptions()));
    }

    /** Confronto affiancato di 2–4 schede di produzione. */
    public function confronto(Request $request)
    {
        $ids = collect(explode(',', (string) $request->input('ids')))
            ->map(fn ($i) => (int) trim($i))->filter()->unique()->take(4)->values();

        $schede = SchedaProduzione::with([
            'prodotto.varianti', 'ricette.materiaPrima',
            'flussi.flusso', 'imballaggi.fornitore', 'gas.fornitore',
        ])->whereIn('id', $ids)->get();

        return Inertia::render('Schede/Confronto', [
            'schede' => $schede->map(fn ($s) => [
                'id'             => $s->id,
                'modello'        => $s->modello,
                'revisione'      => $s->revisione,
                'data_revisione' => $s->data_revisione,
                'attiva'         => (bool) $s->attiva,
                'ha_marinatura'  => (bool) $s->ha_marinatura,
                'prodotto'       => $s->prodotto?->nome,
                'varianti'       => $s->prodotto?->varianti->map(fn ($v) => [
                    'codice' => $v->codice_prodotto, 'pezzatura' => $v->pezzatura_label,
                ])->values() ?? [],
                'ricette'        => $s->ricette->map(fn ($r) => [
                    'materia' => $r->materiaPrima?->nome, 'percentuale' => $r->percentuale, 'grammi_per_kg' => $r->grammi_per_kg,
                ])->values(),
                'imballaggi'     => $s->imballaggi->map(fn ($i) => $i->componente)->values(),
                'gas'            => $s->gas->map(fn ($g) => $g->nome)->values(),
                'ciclo'          => $s->flussi->map(fn ($f) => [
                    'numero' => $f->flusso?->numero, 'nome' => $f->flusso?->nome,
                ])->values(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);

        // GAP-D7: attivando una nuova revisione, disattiva le precedenti dello stesso prodotto
        if (!empty($data['attiva'])) {
            SchedaProduzione::where('prodotto_id', $data['prodotto_id'])
                ->where('attiva', true)
                ->update(['attiva' => false]);
        }

        $scheda = SchedaProduzione::create($this->schedaAttributes($data));
        $this->syncFigli($scheda, $data);

        return redirect()->route('schede.index')->with('success', 'Scheda creata con successo.');
    }

    public function edit(SchedaProduzione $schede)
    {
        $schede->load([
            'ricette.materiaPrima', 'ricetteMarinature.materiaPrima',
            'flussi.flusso', 'imballaggi', 'gas',
        ]);

        return Inertia::render('Schede/Form', array_merge(['scheda' => $schede], $this->formOptions()));
    }

    public function update(Request $request, SchedaProduzione $schede)
    {
        $data = $this->validateRequest($request);

        $schede->update($this->schedaAttributes($data));
        $this->syncFigli($schede, $data);

        return redirect()->route('schede.index')->with('success', 'Scheda aggiornata.');
    }

    public function destroy(SchedaProduzione $schede)
    {
        $schede->delete();

        return redirect()->route('schede.index')->with('success', 'Scheda eliminata.');
    }

    /** Stampa PDF del template vuoto della scheda (da compilare a mano). */
    public function pdfVuota(SchedaProduzione $schede)
    {
        $schede->load([
            'prodotto.varianti', 'ricette.materiaPrima', 'ricette.fornitore',
            'flussi.flusso', 'imballaggi.fornitore', 'gas.fornitore',
        ]);

        $pdf = Pdf::loadView('pdf.scheda-produzione-vuota', [
            'scheda'   => $schede,
            'campioni' => config('haccp.metal_detector_campioni', []),
        ])->setPaper('a4', 'portrait');

        $nome = str_replace([' ', '/'], '_', $schede->modello . '_rev' . $schede->revisione);

        return $pdf->stream('scheda_vuota_' . $nome . '.pdf');
    }

    public function print(SchedaProduzione $schede)
    {
        $schede->load([
            'prodotto.varianti', 'ricette.materiaPrima', 'ricetteMarinature.materiaPrima',
            'flussi.flusso', 'imballaggi.fornitore', 'gas.fornitore',
        ]);

        return Inertia::render('Schede/Print', ['scheda' => $schede]);
    }

    // ---- helpers ---------------------------------------------------------

    private function formOptions(): array
    {
        return [
            'prodotti'  => Prodotto::with('varianti')->where('attivo', true)->orderBy('nome')->get(['id', 'nome'])
                ->map(fn ($p) => [
                    'id'              => $p->id,
                    'nome'            => $p->nome,
                    'codice_prodotto' => $p->varianti->pluck('codice_prodotto')->filter()->implode(', '),
                    'varianti'        => $p->varianti->map(fn ($v) => [
                        'id' => $v->id, 'codice_prodotto' => $v->codice_prodotto,
                        'pezzatura_valore' => $v->pezzatura_valore, 'pezzatura_um' => $v->pezzatura_um,
                        'pezzatura_label' => $v->pezzatura_label,
                    ]),
                ]),
            'materie'   => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'fornitori' => Fornitore::orderBy('ragione_sociale')->get(['id', 'ragione_sociale', 'tipo']),
            'flussi'    => FlussoProduzione::orderBy('numero')->get(['id', 'numero', 'nome', 'controllo', 'misura']),
        ];
    }

    private function schedaAttributes(array $data): array
    {
        return [
            'prodotto_id'    => $data['prodotto_id'],
            'modello'        => $data['modello'],
            'revisione'      => $data['revisione'],
            'data_revisione' => $data['data_revisione'],
            'ha_marinatura'  => $data['ha_marinatura'] ?? false,
            'attiva'         => $data['attiva'] ?? true,
            'note'           => $data['note'] ?? null,
        ];
    }

    private function syncFigli(SchedaProduzione $scheda, array $data): void
    {
        $scheda->ricette()->delete();
        foreach ($data['ricette'] ?? [] as $i => $r) {
            $scheda->ricette()->create([
                'materia_prima_id' => $r['materia_prima_id'],
                'fornitore_id'     => $r['fornitore_id'] ?? null,
                'percentuale'      => $r['percentuale'] ?? null,
                'grammi_per_kg'    => $r['grammi_per_kg'] ?? null,
                'um'               => $r['um'] ?? null,
                'ordine'           => $i + 1,
            ]);
        }

        $scheda->ricetteMarinature()->delete();
        foreach ($data['ricette_marinature'] ?? [] as $i => $r) {
            $scheda->ricetteMarinature()->create([
                'materia_prima_id' => $r['materia_prima_id'],
                'fornitore_id'     => $r['fornitore_id'] ?? null,
                'litri_grammi'     => $r['litri_grammi'] ?? null,
                'um'               => $r['um'] ?? null,
                'ordine'           => $i + 1,
            ]);
        }

        $scheda->flussi()->delete();
        foreach ($data['scheda_flussi'] ?? [] as $i => $f) {
            $scheda->flussi()->create([
                'flusso_id'        => $f['flusso_id'],
                'valore_controllo' => $f['valore_controllo'] ?? null,
                'tempo_minuti'     => $f['tempo_minuti'] ?? null,
                'ordine'           => $i + 1,
            ]);
        }

        $scheda->imballaggi()->delete();
        foreach ($data['imballaggi'] ?? [] as $i => $r) {
            $scheda->imballaggi()->create([
                'componente'           => $r['componente'],
                'prodotto_variante_id' => $r['prodotto_variante_id'] ?? null,
                'fornitore_id'         => $r['fornitore_id'] ?? null,
                'ordine'               => $i + 1,
            ]);
        }

        $scheda->gas()->delete();
        foreach ($data['gas'] ?? [] as $i => $r) {
            $scheda->gas()->create([
                'nome'         => $r['nome'],
                'fornitore_id' => $r['fornitore_id'] ?? null,
                'ordine'       => $i + 1,
            ]);
        }
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'prodotto_id'    => ['required', 'exists:prodotti,id'],
            'modello'        => ['required', 'string', 'max:20'],
            'revisione'      => ['required', 'integer', 'min:0'],
            'data_revisione' => ['required', 'date'],
            'ha_marinatura'  => ['boolean'],
            'attiva'         => ['boolean'],
            'note'           => ['nullable', 'string'],
            'ricette'        => ['array'],
            'ricette.*.materia_prima_id' => ['required', 'exists:materie_prime,id'],
            'ricette.*.fornitore_id'     => ['nullable', 'exists:fornitori,id'],
            'ricette.*.percentuale'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ricette.*.grammi_per_kg'    => ['nullable', 'numeric', 'min:0'],
            'ricette.*.um'               => ['nullable', 'string', 'max:10'],
            'ricette_marinature'         => ['array'],
            'ricette_marinature.*.materia_prima_id' => ['required', 'exists:materie_prime,id'],
            'ricette_marinature.*.fornitore_id'     => ['nullable', 'exists:fornitori,id'],
            'ricette_marinature.*.litri_grammi'     => ['nullable', 'numeric', 'min:0'],
            'ricette_marinature.*.um'               => ['nullable', 'string', 'max:10'],
            'scheda_flussi'              => ['array'],
            'scheda_flussi.*.flusso_id'        => ['required', 'exists:flussi_produzione,id'],
            'scheda_flussi.*.valore_controllo' => ['nullable', 'string', 'max:100'],
            'scheda_flussi.*.tempo_minuti'     => ['nullable', 'integer', 'min:0'],
            'imballaggi'                        => ['array'],
            'imballaggi.*.componente'           => ['required', 'string', 'max:200'],
            'imballaggi.*.prodotto_variante_id' => ['nullable', 'exists:prodotto_varianti,id'],
            'imballaggi.*.fornitore_id'         => ['nullable', 'exists:fornitori,id'],
            'gas'                => ['array'],
            'gas.*.nome'         => ['required', 'string', 'max:200'],
            'gas.*.fornitore_id' => ['nullable', 'exists:fornitori,id'],
        ]);
    }
}
