<?php

namespace App\Http\Controllers;

use App\Models\NotaCredito;
use App\Models\Vendita;
use App\Models\BollaReso;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotaCreditoController extends Controller
{
    public function index(Request $request)
    {
        $query = NotaCredito::with(['vendita.cliente', 'bollaReso']);

        if ($search = $request->input('search')) {
            $query->where('numero_documento', 'ilike', "%{$search}%");
        }

        $note = $query->orderByDesc('data_documento')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('NoteCredito/Index', [
            'note'    => $note,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('NoteCredito/Form', [
            'nota'      => null,
            'vendite'   => $this->venditeList(),
            'bolleReso' => BollaReso::orderByDesc('data_reso')->get(['id', 'vendita_riga_id', 'numero_bolla', 'data_reso']),
        ]);
    }

    public function store(Request $request)
    {
        NotaCredito::create($this->validated($request));

        return redirect()->route('note-credito.index')->with('success', 'Nota di credito registrata.');
    }

    public function edit(NotaCredito $noteCredito)
    {
        return Inertia::render('NoteCredito/Form', [
            'nota'      => $noteCredito,
            'vendite'   => $this->venditeList(),
            'bolleReso' => BollaReso::orderByDesc('data_reso')->get(['id', 'vendita_riga_id', 'numero_bolla', 'data_reso']),
        ]);
    }

    public function update(Request $request, NotaCredito $noteCredito)
    {
        $noteCredito->update($this->validated($request));

        return redirect()->route('note-credito.index')->with('success', 'Nota di credito aggiornata.');
    }

    public function destroy(NotaCredito $noteCredito)
    {
        $noteCredito->delete();

        return redirect()->route('note-credito.index')->with('success', 'Nota di credito spostata nel cestino.');
    }

    private function venditeList()
    {
        return Vendita::with('cliente:id,ragione_sociale')
            ->orderByDesc('data_documento')
            ->get(['id', 'cliente_id', 'numero_documento', 'data_documento']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'vendita_id'       => ['nullable', 'exists:vendite,id'],
            'bolla_reso_id'    => ['nullable', 'exists:bolle_reso,id'],
            'numero_documento' => ['required', 'string', 'max:50'],
            'data_documento'   => ['required', 'date'],
            'importo'          => ['nullable', 'numeric', 'min:0'],
            'note'             => ['nullable', 'string'],
        ]);
    }
}
