<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite does not support ALTER TABLE ... ADD CONSTRAINT; only enforce on PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE note_credito ADD CONSTRAINT note_credito_requires_parent CHECK (vendita_id IS NOT NULL OR bolla_reso_id IS NOT NULL)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE note_credito DROP CONSTRAINT IF EXISTS note_credito_requires_parent');
    }
};
