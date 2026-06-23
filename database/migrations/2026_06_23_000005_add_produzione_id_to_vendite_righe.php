<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vendite_righe ADD COLUMN IF NOT EXISTS produzione_id BIGINT NULL REFERENCES produzioni(id) ON DELETE SET NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_vendite_righe_produzione ON vendite_righe(produzione_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_vendite_righe_produzione');
        DB::statement('ALTER TABLE vendite_righe DROP COLUMN IF EXISTS produzione_id');
    }
};
