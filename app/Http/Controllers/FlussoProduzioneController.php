<?php

namespace App\Http\Controllers;

use App\Models\FlussoProduzione;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FlussoProduzioneController extends Controller
{
    public function index()
    {
        $flussi = FlussoProduzione::orderBy('numero')->get();

        return Inertia::render('Flussi/Index', ['flussi' => $flussi]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1'],
            'nome' => ['required', 'string', 'max:100'],
            'controllo' => ['nullable', 'string', 'max:100'],
            'misura' => ['nullable', 'string', 'max:50'],
        ]);

        FlussoProduzione::create($data);

        return redirect()->route('flussi.index')->with('success', 'Flusso aggiunto.');
    }

    public function update(Request $request, FlussoProduzione $flussi)
    {
        $data = $request->validate([
            'numero' => ['required', 'integer', 'min:1'],
            'nome' => ['required', 'string', 'max:100'],
            'controllo' => ['nullable', 'string', 'max:100'],
            'misura' => ['nullable', 'string', 'max:50'],
        ]);

        $flussi->update($data);

        return redirect()->route('flussi.index')->with('success', 'Flusso aggiornato.');
    }

    public function destroy(FlussoProduzione $flussi)
    {
        $flussi->delete();

        return redirect()->route('flussi.index')->with('success', 'Flusso eliminato.');
    }
}
