<?php

namespace App\Services;

use App\Models\AcquistoRiga;
use App\Models\LottoSemilavorato;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes lot balances (remaining stock) on demand.
 *
 * There is no materialised stock column — a lot's balance is derived from the
 * quantity received minus what has been consumed in productions and sold
 * directly. This mirrors the enforcement logic in ProduzioneController so the
 * inventory report and the production form agree.
 */
class InventoryService
{
    /**
     * Balance per purchase lot.
     * balance = received − consumed in productions − sold directly.
     *
     * @return Collection<int,\App\Models\AcquistoRiga> each row gains
     *         consumato_kg, venduto_kg, balance_kg, is_conto_terzi.
     */
    public function purchaseLotBalances(bool $onlyInStock = false): Collection
    {
        $consumed = DB::table('produzioni_materie_prime')
            ->whereNotNull('acquisto_riga_id')
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(quantita_kg) as s'), 'acquisto_riga_id');

        $sold = DB::table('vendite_righe')
            ->whereNotNull('acquisto_riga_id')
            ->groupBy('acquisto_riga_id')
            ->pluck(DB::raw('SUM(quantita_kg) as s'), 'acquisto_riga_id');

        $lots = AcquistoRiga::with(['acquisto.fornitore:id,ragione_sociale,codice'])
            ->orderByDesc('data_in')
            ->get([
                'id', 'acquisto_id', 'nome_prodotto', 'um', 'quantita_kg',
                'lotto', 'lotto_esterno', 'scadenza', 'data_in', 'data_out',
            ])
            ->map(function ($r) use ($consumed, $sold) {
                $r->consumato_kg   = round((float) ($consumed[$r->id] ?? 0), 3);
                $r->venduto_kg     = round((float) ($sold[$r->id] ?? 0), 3);
                $r->balance_kg     = round((float) $r->quantita_kg - $r->consumato_kg - $r->venduto_kg, 3);
                $r->is_conto_terzi = (bool) ($r->acquisto->is_conto_terzi ?? false);
                return $r;
            });

        if ($onlyInStock) {
            $lots = $lots->filter(fn ($r) => $r->balance_kg > 0.001)->values();
        }

        return $lots;
    }

    /**
     * Balance per semi-finished (internal) lot.
     * balance = produced − consumed in downstream productions.
     *
     * @return Collection<int,\App\Models\LottoSemilavorato>
     */
    public function semilavoratoBalances(bool $onlyInStock = false): Collection
    {
        $consumed = DB::table('produzioni_materie_prime')
            ->whereNotNull('semilavorato_id')
            ->groupBy('semilavorato_id')
            ->pluck(DB::raw('SUM(quantita_kg) as s'), 'semilavorato_id');

        $lots = LottoSemilavorato::whereNull('data_out')
            ->orderByDesc('data_produzione')
            ->get(['id', 'produzione_id', 'lotto', 'nome_prodotto', 'quantita_kg', 'data_produzione'])
            ->map(function ($r) use ($consumed) {
                $r->consumato_kg = round((float) ($consumed[$r->id] ?? 0), 3);
                $r->balance_kg   = round((float) $r->quantita_kg - $r->consumato_kg, 3);
                return $r;
            });

        if ($onlyInStock) {
            $lots = $lots->filter(fn ($r) => $r->balance_kg > 0.001)->values();
        }

        return $lots;
    }

    /**
     * Aggregate summary for dashboards / report headers.
     *
     * @return array{lotti_acquisto:int, kg_giacenza_acquisto:float, lotti_semilavorato:int, kg_giacenza_semilavorato:float}
     */
    public function summary(): array
    {
        $pur = $this->purchaseLotBalances(true);
        $sem = $this->semilavoratoBalances(true);

        return [
            'lotti_acquisto'           => $pur->count(),
            'kg_giacenza_acquisto'     => round((float) $pur->sum('balance_kg'), 3),
            'lotti_semilavorato'       => $sem->count(),
            'kg_giacenza_semilavorato' => round((float) $sem->sum('balance_kg'), 3),
        ];
    }
}
