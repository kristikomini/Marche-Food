<?php

namespace App\Http\Controllers;

use App\Models\DestinazioneIngrediente;
use App\Models\Prodotto;
use App\Models\MateriaPrima;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DestinazioneIngredientiController extends Controller
{
    public function index()
    {
        $destinazioni = DestinazioneIngrediente::with(['prodotto', 'materiaPrima'])
            ->join('prodotti', 'prodotti.id', '=', 'destinazione_ingredienti.prodotto_id')
            ->join('materie_prime', 'materie_prime.id', '=', 'destinazione_ingredienti.materia_prima_id')
            ->orderBy('prodotti.nome')
            ->orderBy('materie_prime.nome')
            ->select('destinazione_ingredienti.*')
            ->get();

        return Inertia::render('DestinazioneIngredienti/Index', [
            'destinazioni' => $destinazioni,
            'prodotti'     => Prodotto::with('varianti')->where('attivo', true)->orderBy('nome')->get(['id', 'nome'])
                ->map(fn ($p) => [
                    'id'              => $p->id,
                    'nome'            => $p->nome,
                    'codice_prodotto' => $p->varianti->pluck('codice_prodotto')->filter()->implode(', '),
                ]),
            'materie'      => MateriaPrima::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'prodotto_id'      => ['required', 'exists:prodotti,id'],
            'materia_prima_id' => ['required', 'exists:materie_prime,id'],
        ]);

        DestinazioneIngrediente::firstOrCreate($data);

        return redirect()->route('destinazione-ingredienti.index')->with('success', 'Destinazione aggiunta.');
    }

    public function destroy(DestinazioneIngrediente $destinazioneIngredienti)
    {
        $destinazioneIngredienti->delete();

        return redirect()->route('destinazione-ingredienti.index')->with('success', 'Destinazione rimossa.');
    }
}
