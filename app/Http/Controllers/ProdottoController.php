<?php

namespace App\Http\Controllers;

use App\Models\Prodotto;
use App\Models\UnitaMisura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProdottoController extends Controller
{
    public function index(Request $request)
    {
        $query = Prodotto::query()->with('varianti');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'ilike', "%{$search}%")
                  ->orWhereHas('varianti', fn ($v) => $v->where('codice_prodotto', 'ilike', "%{$search}%"));
            });
        }

        $prodotti = $query->orderBy('nome')->paginate(25)->withQueryString();

        return Inertia::render('Prodotti/Index', [
            'prodotti' => $prodotti,
            'filters'  => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Prodotti/Form', [
            'prodotto'  => null,
            'umOptions' => $this->umOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertCodiciUnivoci($data['varianti']);

        $prodotto = Prodotto::create([
            'nome'   => $data['nome'],
            'attivo' => $data['attivo'] ?? true,
            'note'   => $data['note'] ?? null,
        ]);

        $this->syncVarianti($prodotto, $data['varianti']);

        return redirect()->route('prodotti.index')->with('success', 'Prodotto creato.');
    }

    public function edit(Prodotto $prodotto)
    {
        $prodotto->load('varianti');

        return Inertia::render('Prodotti/Form', [
            'prodotto'  => $prodotto,
            'umOptions' => $this->umOptions(),
        ]);
    }

    public function update(Request $request, Prodotto $prodotto)
    {
        $data = $this->validated($request, $prodotto->id);
        $this->assertCodiciUnivoci($data['varianti']);

        $prodotto->update([
            'nome'   => $data['nome'],
            'attivo' => $data['attivo'] ?? true,
            'note'   => $data['note'] ?? null,
        ]);

        $this->syncVarianti($prodotto, $data['varianti']);

        return redirect()->route('prodotti.index')->with('success', 'Prodotto aggiornato.');
    }

    public function destroy(Prodotto $prodotto)
    {
        $prodotto->delete();

        return redirect()->route('prodotti.index')->with('success', 'Prodotto eliminato.');
    }

    /** Riscrive le varianti del prodotto (delete + recreate ordinato). */
    private function syncVarianti(Prodotto $prodotto, array $varianti): void
    {
        $prodotto->varianti()->delete();
        foreach (array_values($varianti) as $i => $v) {
            $prodotto->varianti()->create([
                'codice_prodotto'  => $v['codice_prodotto'],
                'pezzatura_valore' => $v['pezzatura_valore'] ?? null,
                'pezzatura_um'     => $v['pezzatura_um'] ?? null,
                'um_id'            => $v['um_id'] ?? null,
                'descrizione'      => $v['descrizione'] ?? null,
                'ordine'           => $i,
                'attiva'           => $v['attiva'] ?? true,
            ]);
        }
    }

    /** Impedisce due varianti con lo stesso codice nello stesso invio. */
    private function assertCodiciUnivoci(array $varianti): void
    {
        $codici = array_map(fn ($v) => strtolower(trim($v['codice_prodotto'] ?? '')), $varianti);
        if (count($codici) !== count(array_unique($codici))) {
            throw ValidationException::withMessages([
                'varianti' => 'Le varianti non possono avere lo stesso codice prodotto.',
            ]);
        }
    }

    private function umOptions(): array
    {
        return UnitaMisura::orderBy('codice')->get(['id', 'codice', 'descrizione'])->toArray();
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nome'   => ['required', 'string', 'max:200'],
            'attivo' => ['boolean'],
            'note'   => ['nullable', 'string'],
            'varianti'                    => ['required', 'array', 'min:1'],
            'varianti.*.codice_prodotto'  => [
                'required', 'string', 'max:20',
                // codice univoco fra prodotti diversi (le varianti del prodotto stesso
                // vengono riscritte al salvataggio e sono escluse dal controllo).
                Rule::unique('prodotto_varianti', 'codice_prodotto')
                    ->when($ignoreId !== null, fn ($rule) => $rule->where(
                        fn ($q) => $q->where('prodotto_id', '!=', $ignoreId)
                    )),
            ],
            'varianti.*.pezzatura_valore' => ['nullable', 'numeric', 'min:0'],
            'varianti.*.pezzatura_um'     => ['nullable', 'string', 'max:10'],
            'varianti.*.um_id'            => ['nullable', 'exists:unita_misura,id'],
            'varianti.*.descrizione'      => ['nullable', 'string', 'max:200'],
            'varianti.*.attiva'           => ['boolean'],
        ]);
    }
}
