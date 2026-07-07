<?php

namespace App\Http\Controllers;

use App\Models\DestinazioneIngrediente;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use App\Services\AllergenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MateriaPrimaController extends Controller
{
    public function index(Request $request)
    {
        $query = MateriaPrima::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhere('codice', 'like', "%{$search}%");
            });
        }

        $materie = $query->orderBy('nome')->paginate(25)->withQueryString();

        return Inertia::render('MateriePrime/Index', [
            'materie'          => $materie,
            'filters'          => $request->only(['search']),
            'allergeniLabels'  => AllergenService::EU_ALLERGENS,
        ]);
    }

    public function create()
    {
        return Inertia::render('MateriePrime/Form', [
            'materia'          => null,
            'allergeniOptions' => AllergenService::options(),
        ]);
    }

    /**
     * Scheda di dettaglio della materia prima:
     *  - i lotti di produzione IN USCITA che l'hanno utilizzata (tracciabilità
     *    diretta: materia prima → produzioni → lotto prodotto);
     *  - l'elenco dei prodotti che la utilizzano (da ricette e destinazione
     *    ingredienti).
     */
    public function show(MateriaPrima $materiePrime)
    {
        $id = $materiePrime->id;

        // 1) Lotti di produzione in uscita collegati a questa materia prima.
        $lottiProduzione = Produzione::query()
            ->whereHas('materiePrime', fn ($q) => $q->where('materia_prima_id', $id))
            ->with('scheda.prodotto')
            ->withSum(
                ['materiePrime as qta_materia_kg' => fn ($q) => $q->where('materia_prima_id', $id)],
                'quantita_kg'
            )
            ->orderByDesc('data_produzione')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => [
                'id'                   => $p->id,
                'lotto_produzione'     => $p->lotto_produzione,
                'data_produzione'      => optional($p->data_produzione)->toDateString(),
                'prodotto'             => $p->scheda?->prodotto?->nome,
                'codice_prodotto'      => $p->scheda?->prodotto?->codice_prodotto,
                'quantita_prodotta_kg' => $p->quantita_prodotta_kg,
                'qta_materia_kg'       => $p->qta_materia_kg,
            ]);

        // 2) Prodotti che utilizzano questa materia prima (ricette + destinazione).
        $daDestinazione = DestinazioneIngrediente::where('materia_prima_id', $id)->pluck('prodotto_id');

        $prodotti = Prodotto::query()
            ->where(function ($q) use ($id, $daDestinazione) {
                $q->whereHas('schede.ricette', fn ($r) => $r->where('materia_prima_id', $id))
                  ->orWhereIn('id', $daDestinazione);
            })
            ->orderBy('nome')
            ->get(['id', 'codice_prodotto', 'nome', 'attivo'])
            ->map(fn ($p) => [
                'id'              => $p->id,
                'codice_prodotto' => $p->codice_prodotto,
                'nome'            => $p->nome,
                'attivo'          => (bool) $p->attivo,
                'in_ricetta'      => $p->schede()->whereHas('ricette', fn ($r) => $r->where('materia_prima_id', $id))->exists(),
                'in_destinazione' => $daDestinazione->contains($p->id),
            ]);

        return Inertia::render('MateriePrime/Show', [
            'materia' => [
                'id'        => $materiePrime->id,
                'codice'    => $materiePrime->codice,
                'nome'      => $materiePrime->nome,
                'allergeni' => $materiePrime->allergeni ?? [],
                'allergeni_tracce' => $materiePrime->allergeni_tracce ?? [],
            ],
            'allergeniLabels' => AllergenService::EU_ALLERGENS,
            'lottiProduzione' => $lottiProduzione,
            'prodotti'        => $prodotti,
        ]);
    }

    public function store(Request $request)
    {
        MateriaPrima::create($this->validated($request));

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima creata.');
    }

    public function edit(MateriaPrima $materiePrime)
    {
        return Inertia::render('MateriePrime/Form', [
            'materia'          => $materiePrime,
            'allergeniOptions' => AllergenService::options(),
        ]);
    }

    public function update(Request $request, MateriaPrima $materiePrime)
    {
        $materiePrime->update($this->validated($request, $materiePrime->id));

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima aggiornata.');
    }

    public function destroy(MateriaPrima $materiePrime)
    {
        $materiePrime->delete();

        return redirect()->route('materie-prime.index')->with('success', 'Materia prima eliminata.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'codice' => ['nullable', 'integer',
                Rule::unique('materie_prime', 'codice')->ignore($ignoreId)],
            'nome'   => ['required', 'string', 'max:200'],
            'allergeni'          => ['nullable', 'array'],
            'allergeni.*'        => ['string', Rule::in(array_keys(AllergenService::EU_ALLERGENS))],
            'allergeni_tracce'   => ['nullable', 'array'],
            'allergeni_tracce.*' => ['string', Rule::in(array_keys(AllergenService::EU_ALLERGENS))],
        ]);
    }
}
