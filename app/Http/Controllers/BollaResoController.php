<?php

namespace App\Http\Controllers;

use App\Models\BollaReso;
use App\Models\Vendita;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BollaResoController extends Controller
{
    public function index(Request $request)
    {
        $query = BollaReso::with(['venditaRiga.vendita.cliente']);

        if ($search = $request->input('search')) {
            $query->where('numero_bolla', 'ilike', "%{$search}%");
        }

        $bolle = $query->orderByDesc('data_reso')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('BolleReso/Index', [
            'bolle'   => $bolle,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('BolleReso/Form', [
            'bolla'   => null,
            'vendite' => $this->venditeConRighe(),
        ]);
    }

    public function store(Request $request)
    {
        BollaReso::create($this->validated($request));

        return redirect()->route('bolle-reso.index')->with('success', 'Bolla reso registrata.');
    }

    public function edit(BollaReso $bolleReso)
    {
        $bolleReso->load('venditaRiga.vendita');

        return Inertia::render('BolleReso/Form', [
            'bolla'   => $bolleReso,
            'vendite' => $this->venditeConRighe(),
        ]);
    }

    public function update(Request $request, BollaReso $bolleReso)
    {
        $bolleReso->update($this->validated($request));

        return redirect()->route('bolle-reso.index')->with('success', 'Bolla reso aggiornata.');
    }

    public function destroy(BollaReso $bolleReso)
    {
        if ($bolleReso->notaCredito()->exists()) {
            return back()->with('error', 'Impossibile eliminare: questa bolla di reso ha una nota di credito collegata. Elimina prima la nota di credito.');
        }

        $bolleReso->delete();

        return redirect()->route('bolle-reso.index')->with('success', 'Bolla reso spostata nel cestino.');
    }

    private function venditeConRighe()
    {
        return Vendita::with(['cliente:id,ragione_sociale', 'righe:id,vendita_id,nome_prodotto,quantita_kg,lotto,lotto_esterno'])
            ->orderByDesc('data_documento')
            ->get(['id', 'cliente_id', 'numero_documento', 'data_documento', 'tipo_documento']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'vendita_riga_id' => ['required', 'exists:vendite_righe,id'],
            'numero_bolla'    => ['nullable', 'string', 'max:50'],
            'quantita_pz'     => ['nullable', 'numeric', 'min:0'],
            'quantita_kg'     => ['required', 'numeric', 'min:0.001'],
            'data_reso'       => ['required', 'date'],
            'note'            => ['nullable', 'string'],
        ]);
    }
}
