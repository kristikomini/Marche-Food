<?php

namespace App\Http\Controllers;

use App\Models\Acquisto;
use App\Models\Produzione;
use App\Models\Vendita;
use App\Services\AllergenService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private AllergenService $allergeni,
    ) {
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

        $allergeni = $this->allergeni->forProduzioneLabels($produzione);

        $pdf = Pdf::loadView('pdf.produzione', compact('produzione', 'allergeni'))->setPaper('a4', 'portrait');
        $filename = 'lavorazione_' . str_replace(['/', ' '], '_', $produzione->lotto_produzione) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Scheda di Produzione (modello M2PO3) — riproduce il modulo cartaceo:
     * dati prodotto, materie prime con lotto/fornitore, imballaggi, gas,
     * ciclo di lavoro e funzionamento metal detector. Le parti compilate a
     * mano (lotti gas, controllo peso, esiti metal detector) restano vuote.
     */
    public function schedaProduzionePdf(Produzione $produzione)
    {
        $produzione->load([
            'scheda.prodotto',
            'materiePrime.materiaPrima',
            'materiePrime.acquistoRiga.acquisto.fornitore',
            'materiePrime.semilavorato',
            'imballaggiPrimari.lottoImballaggio.fornitore',
        ]);

        $pdf = Pdf::loadView('pdf.scheda-produzione', compact('produzione'))->setPaper('a4', 'portrait');
        $filename = 'scheda_produzione_' . str_replace(['/', ' '], '_', $produzione->lotto_produzione) . '.pdf';

        return $pdf->stream($filename);
    }

    public function acquistoPdf(Acquisto $acquisto)
    {
        $acquisto->load(['fornitore', 'righe']);
        $pdf = Pdf::loadView('pdf.acquisto', compact('acquisto'))->setPaper('a4', 'portrait');

        return $pdf->download('acquisto_' . str_replace(['/', ' '], '_', $acquisto->numero_documento) . '.pdf');
    }

    public function produzioneEtichetta(\Illuminate\Http\Request $request, Produzione $produzione)
    {
        $produzione->load('scheda.prodotto');
        $copie = max(1, min(60, (int) $request->input('copie', 1)));

        return view('labels.produzione', [
            'lotto'          => $produzione->lotto_produzione,
            'prodotto'       => $produzione->scheda?->prodotto?->nome,
            'dataProduzione' => \Carbon\Carbon::parse($produzione->data_produzione)->format('d/m/Y'),
            'quantita'       => $produzione->quantita_prodotta_kg,
            'traceUrl'       => url('/tracciabilita?q=' . urlencode($produzione->lotto_produzione)),
            'allergeni'      => $this->allergeni->forProduzioneLabels($produzione),
            'copie'          => $copie,
        ]);
    }

    public function venditaPdf(Vendita $vendita)
    {
        $vendita->load(['cliente', 'righe']);
        $pdf = Pdf::loadView('pdf.vendita', compact('vendita'))->setPaper('a4', 'portrait');

        return $pdf->download('vendita_' . str_replace(['/', ' '], '_', $vendita->numero_documento) . '.pdf');
    }

    // ── Lot QR labels for purchases / sales ─────────────────────────────────
    public function acquistoEtichette(Request $request, Acquisto $acquisto)
    {
        $acquisto->load(['righe.materiaPrima', 'fornitore']);
        $copie = max(1, min(60, (int) $request->input('copie', 1)));

        $labels = $this->lottoLabels($acquisto->righe, $acquisto->fornitore?->ragione_sociale, $copie);

        return view('labels.lotti', [
            'titolo' => 'Etichette acquisto ' . $acquisto->numero_documento,
            'labels' => $labels,
        ]);
    }

    public function venditaEtichette(Request $request, Vendita $vendita)
    {
        $vendita->load(['righe', 'cliente']);
        $copie = max(1, min(60, (int) $request->input('copie', 1)));

        $labels = $this->lottoLabels($vendita->righe, $vendita->cliente?->ragione_sociale, $copie);

        return view('labels.lotti', [
            'titolo' => 'Etichette vendita ' . $vendita->numero_documento,
            'labels' => $labels,
        ]);
    }

    /**
     * Build QR label rows from document lines. Lines without any lot code are
     * skipped — the QR points to the traceability view keyed on the lot.
     *
     * @param  iterable  $righe
     * @return array<int,array<string,mixed>>
     */
    private function lottoLabels($righe, ?string $controparte, int $copie): array
    {
        return collect($righe)->map(function ($r) use ($controparte, $copie) {
            $lotto = $r->lotto ?: $r->lotto_esterno;
            if (! $lotto) {
                return null;
            }

            $meta = [];
            if ($controparte) {
                $meta[] = $controparte;
            }
            if ($r->quantita_kg) {
                $meta[] = number_format((float) $r->quantita_kg, 3, ',', '.') . ' kg';
            }
            if ($r->scadenza) {
                $meta[] = 'Scad.: ' . \Carbon\Carbon::parse($r->scadenza)->format('d/m/Y');
            }

            // Allergens flow onto the label when the lot is linked to a raw
            // material (materiaPrima is eager-loaded for purchases only).
            $mp = $r->materiaPrima ?? null;

            return [
                'prodotto'  => $r->nome_prodotto,
                'lotto'     => $lotto,
                'meta'      => implode(' · ', $meta),
                'traceUrl'  => url('/tracciabilita?q=' . urlencode($lotto)),
                'allergeni' => $mp ? $this->allergeni->labels($mp->allergeni ?? []) : [],
                'tracce'    => $mp ? $this->allergeni->labels($mp->allergeni_tracce ?? []) : [],
                'copie'     => $copie,
            ];
        })->filter()->values()->all();
    }
}
