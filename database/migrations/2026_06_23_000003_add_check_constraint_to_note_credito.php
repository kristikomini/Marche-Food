<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // GAP-D4: prevent orphaned credit notes with no parent reference
        DB::statement('ALTER TABLE note_credito ADD CONSTRAINT note_credito_requires_parent CHECK (vendita_id IS NOT NULL OR bolla_reso_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE note_credito DROP CONSTRAINT IF EXISTS note_credito_requires_parent');
    }
};
