<?php

namespace App\Http\Controllers;

use App\Models\Produzione;
use App\Models\VenditaRiga;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecallController extends Controller
{
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));

        $produzioni    = collect();
        $venditeRighe  = collect();

        if ($query !== '') {
            // Find matching productions
            $produzioni = Produzione::with('scheda.prodotto')
                ->where('lotto_produzione', 'ilike', "%{$query}%")
                ->orderByDesc('data_produzione')
                ->limit(20)
                ->get();

            // For each matched production, find sales via string lot match OR FK
            $lottiProduzione = $produzioni->pluck('lotto_produzione')->all();

            $venditeRighe = VenditaRiga::with(['vendita.cliente'])
                ->where(function ($q) use ($lottiProduzione, $query) {
                    $q->whereIn('lotto', $lottiProduzione)
                      ->orWhereIn('lotto_esterno', $lottiProduzione);
                    // If a production lot was passed directly, also match partial
                    if (!empty($query)) {
                        $q->orWhere('lotto', 'ilike', "%{$query}%")
                          ->orWhere('lotto_esterno', 'ilike', "%{$query}%");
                    }
                })
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        return Inertia::render('Recall/Index', [
            'q'            => $query,
            'produzioni'   => $produzioni,
            'venditeRighe' => $venditeRighe,
        ]);
    }
}
