<?php

namespace App\Http\Controllers;

use App\Models\Produzione;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function produzionePdf(Produzione $produzione)
    {
        $produzione->load([
            'scheda.prodotto',
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
        ]);

        $pdf = Pdf::loadView('pdf.produzione', compact('produzione'))
            ->setPaper('a4', 'portrait');

        $filename = 'lavorazione_' . str_replace(['/', ' '], '_', $produzione->lotto_produzione) . '.pdf';

        return $pdf->download($filename);
    }
}
