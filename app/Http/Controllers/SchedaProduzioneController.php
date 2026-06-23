<?php

namespace App\Http\Controllers;

use App\Models\SchedaProduzione;
use App\Models\Prodotto;
use App\Models\MateriaPrima;
use App\Models\FlussoProduzione;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchedaProduzioneController extends Controller
{
    public function index(Request $request)
    {
        $query = SchedaProduzione::with('prodotto');

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
        return Inertia::render('Schede/Form', [
            'scheda'   => null,
            'prodotti' => Prodotto::where('attivo', true)->orderBy('nome')->get(['id', 'codice_prodotto', 'nome']),
            'materie'  => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'flussi'   => FlussoProduzione::orderBy('numero')->get(['id', 'numero', 'nome', 'controllo', 'misura']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);

        // GAP-D7: when creating a new active revision, deactivate all prior revisions for the same product
        if (!empty($data['attiva'])) {
            SchedaProduzione::where('prodotto_id', $data['prodotto_id'])
                ->where('attiva', true)
                ->update(['attiva' => false]);
        }

        $scheda = SchedaProduzione::create([
            'prodotto_id'    => $data['prodotto_id'],
            'modello'        => $data['modello'],
            'revisione'      => $data['revisione'],
            'data_revisione' => $data['data_revisione'],
            'ha_marinatura'  => $data['ha_marinatura'],
            'attiva'         => $data['attiva'],
            'note'           => $data['note'] ?? null,
        ]);

        $this->syncRicette($scheda, $data);

        return redirect()->route('schede.index')->with('success', 'Scheda creata con successo.');
    }

    public function edit(SchedaProduzione $schede)
    {
        $schede->load(['ricette.materiaPrima', 'ricetteMarinature.materiaPrima', 'flussi.flusso']);

        return Inertia::render('Schede/Form', [
            'scheda'   => $schede,
            'prodotti' => Prodotto::where('attivo', true)->orderBy('nome')->get(['id', 'codice_prodotto', 'nome']),
            'materie'  => MateriaPrima::orderBy('nome')->get(['id', 'codice', 'nome']),
            'flussi'   => FlussoProduzione::orderBy('numero')->get(['id', 'numero', 'nome', 'controllo', 'misura']),
        ]);
    }

    public function update(Request $request, SchedaProduzione $schede)
    {
        $data = $this->validateRequest($request);

        $schede->update([
            'prodotto_id'    => $data['prodotto_id'],
            'modello'        => $data['modello'],
            'revisione'      => $data['revisione'],
            'data_revisione' => $data['data_revisione'],
            'ha_marinatura'  => $data['ha_marinatura'],
            'attiva'         => $data['attiva'],
            'note'           => $data['note'] ?? null,
        ]);

        $this->syncRicette($schede, $data);

        return redirect()->route('schede.index')->with('success', 'Scheda aggiornata.');
    }

    public function destroy(SchedaProduzione $schede)
    {
        $schede->delete();

        return redirect()->route('schede.index')->with('success', 'Scheda eliminata.');
    }

    private function syncRicette(SchedaProduzione $scheda, array $data): void
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
            'ricette.*.percentuale'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ricette.*.grammi_per_kg'    => ['nullable', 'numeric', 'min:0'],
            'ricette.*.um'               => ['nullable', 'string', 'max:10'],
            'ricette_marinature'         => ['array'],
            'ricette_marinature.*.materia_prima_id' => ['required', 'exists:materie_prime,id'],
            'ricette_marinature.*.litri_grammi'     => ['nullable', 'numeric', 'min:0'],
            'ricette_marinature.*.um'               => ['nullable', 'string', 'max:10'],
            'scheda_flussi'              => ['array'],
            'scheda_flussi.*.flusso_id'        => ['required', 'exists:flussi_produzione,id'],
            'scheda_flussi.*.valore_controllo' => ['nullable', 'string', 'max:100'],
            'scheda_flussi.*.tempo_minuti'     => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function print(SchedaProduzione $schede)
    {
        $schede->load(['prodotto', 'ricette.materiaPrima', 'ricetteMarinature.materiaPrima', 'flussi.flusso']);

        return Inertia::render('Schede/Print', ['scheda' => $schede]);
    }
}
