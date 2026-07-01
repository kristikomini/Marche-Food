<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Giacenze di magazzino (stock report) — lot balances for purchase lots and
 * semi-finished lots, derived on demand by InventoryService.
 */
class MagazzinoController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $onlyInStock = $request->boolean('solo_giacenza', true);

        $acquisti     = $this->inventory->purchaseLotBalances($onlyInStock);
        $semilavorati = $this->inventory->semilavoratoBalances($onlyInStock);

        return Inertia::render('Magazzino/Index', [
            'acquisti'     => $acquisti,
            'semilavorati' => $semilavorati,
            'summary'      => $this->inventory->summary(),
            'filters'      => ['solo_giacenza' => $onlyInStock],
        ]);
    }

    public function export(Request $request)
    {
        $onlyInStock = $request->boolean('solo_giacenza', true);
        $acquisti    = $this->inventory->purchaseLotBalances($onlyInStock);
        $semi        = $this->inventory->semilavoratoBalances($onlyInStock);

        $filename = 'giacenze_' . now()->format('Ymd_His') . '.csv';

        $callback = function () use ($acquisti, $semi) {
            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Tipo', 'Prodotto', 'Fornitore', 'Lotto', 'Ricevuto (kg)', 'Consumato (kg)', 'Venduto (kg)', 'Giacenza (kg)', 'Scadenza', 'Conto terzi'], ';');
            foreach ($acquisti as $r) {
                fputcsv($h, [
                    'Acquisto',
                    $r->nome_prodotto,
                    $r->acquisto?->fornitore?->ragione_sociale,
                    $r->lotto ?: $r->lotto_esterno,
                    $r->quantita_kg,
                    $r->consumato_kg,
                    $r->venduto_kg,
                    $r->balance_kg,
                    $r->scadenza,
                    $r->is_conto_terzi ? 'Sì' : 'No',
                ], ';');
            }
            foreach ($semi as $r) {
                fputcsv($h, [
                    'Semilavorato',
                    $r->nome_prodotto,
                    '—',
                    $r->lotto,
                    $r->quantita_kg,
                    $r->consumato_kg,
                    '',
                    $r->balance_kg,
                    '',
                    'No',
                ], ';');
            }
            fclose($h);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
