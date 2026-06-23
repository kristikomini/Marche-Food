<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // GAP-T3: 13 missing FK indexes that cause sequential scans
        DB::statement('CREATE INDEX IF NOT EXISTS idx_acquisti_righe_acquisto     ON acquisti_righe(acquisto_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_vendite_righe_vendita       ON vendite_righe(vendita_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_bolle_reso_vendita_riga     ON bolle_reso(vendita_riga_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_note_credito_vendita        ON note_credito(vendita_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_note_credito_bolla          ON note_credito(bolla_reso_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_schede_flussi_scheda        ON schede_produzione_flussi(scheda_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_schede_flussi_flusso        ON schede_produzione_flussi(flusso_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ricette_scheda              ON ricette(scheda_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ricette_mp                  ON ricette(materia_prima_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ricette_mar_scheda          ON ricette_marinature(scheda_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_prod_mp_materia             ON produzioni_materie_prime(materia_prima_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_imb_primari_fornitore       ON lotti_imballaggi_primari(fornitore_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_detergenti_fornitore        ON lotti_detergenti(fornitore_id)');

        // GAP-T6: vendite_righe.lotto_esterno was missing its index
        DB::statement('CREATE INDEX IF NOT EXISTS idx_vendite_righe_lotto_ext     ON vendite_righe(lotto_esterno)');
    }

    public function down(): void
    {
        $indexes = [
            'idx_acquisti_righe_acquisto', 'idx_vendite_righe_vendita', 'idx_bolle_reso_vendita_riga',
            'idx_note_credito_vendita', 'idx_note_credito_bolla', 'idx_schede_flussi_scheda',
            'idx_schede_flussi_flusso', 'idx_ricette_scheda', 'idx_ricette_mp',
            'idx_ricette_mar_scheda', 'idx_prod_mp_materia', 'idx_imb_primari_fornitore',
            'idx_detergenti_fornitore', 'idx_vendite_righe_lotto_ext',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
