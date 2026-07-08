<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reform 2026-07-08 · Fase 0 — indici FK mancanti individuati nella gap analysis
 * (docs/REFORM-PLAN-2026-07-08.md, Parte 4.1). Idempotenti su PostgreSQL e SQLite.
 */
return new class extends Migration {
    private array $indexes = [
        'idx_vendite_righe_acquisto_riga' => 'vendite_righe(acquisto_riga_id)',
        'idx_lotti_semilav_produzione'    => 'lotti_semilavorati(produzione_id)',
        // NB: prodotti.um_id viene spostato su prodotto_varianti in Fase 1 (indice creato lì).
        'idx_materie_prime_um'            => 'materie_prime(um_id)',
        'idx_acquisti_righe_prodotto'     => 'acquisti_righe(prodotto_id)',
        'idx_vendite_righe_prodotto'      => 'vendite_righe(prodotto_id)',
        'idx_dest_ingred_materia'         => 'destinazione_ingredienti(materia_prima_id)',
        'idx_ricette_fornitore'           => 'ricette(fornitore_id)',
        'idx_ricette_mar_materia'         => 'ricette_marinature(materia_prima_id)',
        'idx_ricette_mar_fornitore'       => 'ricette_marinature(fornitore_id)',
        'idx_recall_notifiche_cliente'    => 'recall_notifiche(cliente_id)',
        'idx_recall_notifiche_vendita'    => 'recall_notifiche(vendita_riga_id)',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$target}");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
