<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Management / compliance reporting: date-range aggregates over purchases,
 * sales and productions, plus per-supplier / per-customer breakdowns and an
 * expiry report. Conto-terzi purchases are excluded from owned-volume figures.
 */
class ReportService
{
    /**
     * @return array{da:string,a:string}
     */
    public function normalizeRange(?string $da, ?string $a): array
    {
        $to   = $a ? Carbon::parse($a) : Carbon::now();
        $from = $da ? Carbon::parse($da) : (clone $to)->subMonths(1)->startOfMonth();

        return ['da' => $from->toDateString(), 'a' => $to->toDateString()];
    }

    public function managementSummary(?string $da, ?string $a): array
    {
        ['da' => $da, 'a' => $a] = $this->normalizeRange($da, $a);

        $acquisti = DB::table('acquisti')
            ->whereNull('deleted_at')
            ->whereBetween('data_documento', [$da, $a])
            ->where('is_conto_terzi', false);
        $acquistiKg = DB::table('acquisti_righe')
            ->join('acquisti', 'acquisti.id', '=', 'acquisti_righe.acquisto_id')
            ->whereNull('acquisti.deleted_at')
            ->whereBetween('acquisti.data_documento', [$da, $a])
            ->where('acquisti.is_conto_terzi', false)
            ->sum('acquisti_righe.quantita_kg');

        $vendite   = DB::table('vendite')->whereNull('deleted_at')->whereBetween('data_documento', [$da, $a]);
        $venditeKg = DB::table('vendite_righe')
            ->join('vendite', 'vendite.id', '=', 'vendite_righe.vendita_id')
            ->whereNull('vendite.deleted_at')
            ->whereBetween('vendite.data_documento', [$da, $a])
            ->sum('vendite_righe.quantita_kg');

        $produzioni   = DB::table('produzioni')->whereNull('deleted_at')->whereBetween('data_produzione', [$da, $a]);
        $produzioniKg = (clone $produzioni)->sum('quantita_prodotta_kg');

        return [
            'da'    => $da,
            'a'     => $a,
            'totali' => [
                'acquisti_docs'  => (clone $acquisti)->count(),
                'acquisti_kg'    => round((float) $acquistiKg, 3),
                'vendite_docs'   => (clone $vendite)->count(),
                'vendite_kg'     => round((float) $venditeKg, 3),
                'produzioni'     => (clone $produzioni)->count(),
                'produzioni_kg'  => round((float) $produzioniKg, 3),
            ],
            'per_fornitore' => $this->perFornitore($da, $a),
            'per_cliente'   => $this->perCliente($da, $a),
        ];
    }

    private function perFornitore(string $da, string $a): array
    {
        return DB::table('acquisti_righe')
            ->join('acquisti', 'acquisti.id', '=', 'acquisti_righe.acquisto_id')
            ->join('fornitori', 'fornitori.id', '=', 'acquisti.fornitore_id')
            ->whereNull('acquisti.deleted_at')
            ->whereBetween('acquisti.data_documento', [$da, $a])
            ->where('acquisti.is_conto_terzi', false)
            ->groupBy('fornitori.id', 'fornitori.ragione_sociale')
            ->orderByDesc(DB::raw('SUM(acquisti_righe.quantita_kg)'))
            ->limit(20)
            ->get([
                'fornitori.ragione_sociale as nome',
                DB::raw('COUNT(DISTINCT acquisti.id) as documenti'),
                DB::raw('ROUND(SUM(acquisti_righe.quantita_kg), 3) as kg'),
            ])->toArray();
    }

    private function perCliente(string $da, string $a): array
    {
        return DB::table('vendite_righe')
            ->join('vendite', 'vendite.id', '=', 'vendite_righe.vendita_id')
            ->join('clienti', 'clienti.id', '=', 'vendite.cliente_id')
            ->whereNull('vendite.deleted_at')
            ->whereBetween('vendite.data_documento', [$da, $a])
            ->groupBy('clienti.id', 'clienti.ragione_sociale')
            ->orderByDesc(DB::raw('SUM(vendite_righe.quantita_kg)'))
            ->limit(20)
            ->get([
                'clienti.ragione_sociale as nome',
                DB::raw('COUNT(DISTINCT vendite.id) as documenti'),
                DB::raw('ROUND(SUM(vendite_righe.quantita_kg), 3) as kg'),
            ])->toArray();
    }

    /**
     * Lots still in stock (data_out IS NULL) with an expiry date, flagged by
     * status. $entroGiorni controls the "in scadenza" window (default 30).
     */
    public function scadenzeReport(int $entroGiorni = 30): array
    {
        $oggi   = Carbon::now()->toDateString();
        $limite = Carbon::now()->addDays($entroGiorni)->toDateString();

        $rows = DB::table('acquisti_righe')
            ->join('acquisti', 'acquisti.id', '=', 'acquisti_righe.acquisto_id')
            ->join('fornitori', 'fornitori.id', '=', 'acquisti.fornitore_id')
            ->whereNull('acquisti.deleted_at')
            ->whereNull('acquisti_righe.data_out')
            ->whereNotNull('acquisti_righe.scadenza')
            ->where('acquisti_righe.scadenza', '<=', $limite)
            ->orderBy('acquisti_righe.scadenza')
            ->get([
                'acquisti_righe.nome_prodotto',
                'acquisti_righe.lotto',
                'acquisti_righe.lotto_esterno',
                'acquisti_righe.quantita_kg',
                'acquisti_righe.scadenza',
                'fornitori.ragione_sociale as fornitore',
            ]);

        return $rows->map(function ($r) use ($oggi) {
            $r->stato = $r->scadenza < $oggi ? 'scaduto' : 'in_scadenza';
            return (array) $r;
        })->toArray();
    }
}
