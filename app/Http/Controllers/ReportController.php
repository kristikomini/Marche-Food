<?php

namespace App\Http\Controllers;

use App\Models\Acquisto;
use App\Models\Produzione;
use App\Models\Vendita;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    // ── Management / compliance report ──────────────────────────────────────
    public function index(Request $request)
    {
        $summary  = $this->reports->managementSummary($request->input('da'), $request->input('a'));
        $scadenze = $this->reports->scadenzeReport(30);

        return Inertia::render('Report/Index', [
            'summary'  => $summary,
            'scadenze' => $scadenze,
            'filters'  => ['da' => $summary['da'], 'a' => $summary['a']],
        ]);
    }

    public function pdf(Request $request)
    {
        $summary  = $this->reports->managementSummary($request->input('da'), $request->input('a'));
        $scadenze = $this->reports->scadenzeReport(30);

        $pdf = Pdf::loadView('pdf.report', compact('summary', 'scadenze'))->setPaper('a4', 'portrait');

        return $pdf->download('report_gestionale_' . $summary['da'] . '_' . $summary['a'] . '.pdf');
    }

    public function csv(Request $request)
    {
        $summary = $this->reports->managementSummary($request->input('da'), $request->input('a'));

        $callback = function () use ($summary) {
            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Report gestionale', $summary['da'] . ' → ' . $summary['a']], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Metrica', 'Documenti', 'Kg'], ';');
            fputcsv($h, ['Acquisti', $summary['totali']['acquisti_docs'], $summary['totali']['acquisti_kg']], ';');
            fputcsv($h, ['Vendite', $summary['totali']['vendite_docs'], $summary['totali']['vendite_kg']], ';');
            fputcsv($h, ['Produzioni', $summary['totali']['produzioni'], $summary['totali']['produzioni_kg']], ';');
            fputcsv($h, [], ';');
            fputcsv($h, ['Acquisti per fornitore'], ';');
            fputcsv($h, ['Fornitore', 'Documenti', 'Kg'], ';');
            foreach ($summary['per_fornitore'] as $r) {
                fputcsv($h, [$r->nome ?? '', $r->documenti ?? '', $r->kg ?? ''], ';');
            }
            fputcsv($h, [], ';');
            fputcsv($h, ['Vendite per cliente'], ';');
            fputcsv($h, ['Cliente', 'Documenti', 'Kg'], ';');
            foreach ($summary['per_cliente'] as $r) {
                fputcsv($h, [$r->nome ?? '', $r->documenti ?? '', $r->kg ?? ''], ';');
            }
            fclose($h);
        };

        return response()->streamDownload($callback, 'report_gestionale_' . $summary['da'] . '_' . $summary['a'] . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Document PDFs ───────────────────────────────────────────────────────
    public function produzionePdf(Produzione $produzione)
    {
        $produzione->load([
            'scheda.prodotto',
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'imballaggiPrimari.lottoImballaggio.fornitore',
            'detergenti.lottoDetergente.fornitore',
        ]);

        $pdf = Pdf::loadView('pdf.produzione', compact('produzione'))->setPaper('a4', 'portrait');
        $filename = 'lavorazione_' . str_replace(['/', ' '], '_', $produzione->lotto_produzione) . '.pdf';

        return $pdf->download($filename);
    }

    public function acquistoPdf(Acquisto $acquisto)
    {
        $acquisto->load(['fornitore', 'righe']);
        $pdf = Pdf::loadView('pdf.acquisto', compact('acquisto'))->setPaper('a4', 'portrait');

        return $pdf->download('acquisto_' . str_replace(['/', ' '], '_', $acquisto->numero_documento) . '.pdf');
    }

    public function venditaPdf(Vendita $vendita)
    {
        $vendita->load(['cliente', 'righe']);
        $pdf = Pdf::loadView('pdf.vendita', compact('vendita'))->setPaper('a4', 'portrait');

        return $pdf->download('vendita_' . str_replace(['/', ' '], '_', $vendita->numero_documento) . '.pdf');
    }
}
