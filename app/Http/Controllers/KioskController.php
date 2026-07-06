<?php

namespace App\Http\Controllers;

use App\Models\AcquistoRiga;
use App\Models\MateriaPrima;
use App\Models\SchedaProduzione;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Tablet "Kiosk Mode" (Epic 1) — a simplified full-screen production entry UI
 * for factory-floor operators. Submission reuses ProduzioneController@store.
 */
class KioskController extends Controller
{
    public function index()
    {
        $schede = SchedaProduzione::with(['prodotto:id,nome', 'ricette.materiaPrima:id,nome'])
            ->where('attiva', true)
            ->orderBy('modello')
            ->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'modello'  => $s->modello,
                'revisione' => $s->revisione,
                'prodotto' => $s->prodotto?->nome,
                'ingredienti' => $s->ricette->map(fn ($r) => [
                    'materia_prima_id' => $r->materia_prima_id,
                    'nome' => $r->materiaPrima?->nome,
                ])->values(),
            ]);

        return Inertia::render('Produzioni/Kiosk', [
            'schede'  => $schede,
            'materie' => MateriaPrima::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /** Resolve a scanned lot code to a purchase lot + remaining balance. */
    public function lookup(Request $request, InventoryService $inventory): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return response()->json(['found' => false, 'messaggio' => 'Codice vuoto.']);
        }

        $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $riga = AcquistoRiga::with('acquisto.fornitore:id,ragione_sociale')
            ->where('lotto', $code)
            ->orWhere('lotto_esterno', $code)
            ->orWhere('lotto', $op, "%{$code}%")
            ->orWhere('lotto_esterno', $op, "%{$code}%")
            ->first();

        if (! $riga) {
            return response()->json(['found' => false, 'messaggio' => "Nessun lotto trovato per «{$code}»."]);
        }

        $balance = $inventory->purchaseLotBalances(false)->firstWhere('id', $riga->id);
        $mp = MateriaPrima::where('nome', $op, $riga->nome_prodotto)->first();

        return response()->json([
            'found' => true,
            'riga'  => [
                'id'            => $riga->id,
                'nome_prodotto' => $riga->nome_prodotto,
                'lotto'         => $riga->lotto ?: $riga->lotto_esterno,
                'fornitore'     => $riga->acquisto?->fornitore?->ragione_sociale,
                'balance_kg'    => $balance?->balance_kg ?? (float) $riga->quantita_kg,
            ],
            'materia_prima_id'   => $mp?->id,
            'materia_prima_nome' => $mp?->nome,
        ]);
    }
}
