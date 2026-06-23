<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Flag on each purchase document to mark third-party (customer-owned) materials.
        // These must be excluded from financial reports and dashboard stock totals.
        Schema::table('acquisti', function (Blueprint $table) {
            $table->boolean('is_conto_terzi')->default(false)->after('note');
        });

        // fornitori.tipo is varchar(30) — no ENUM alteration needed.
        // Adding a CHECK constraint to enforce the allowed set including the new value.
        DB::statement("ALTER TABLE fornitori ADD CONSTRAINT fornitori_tipo_values CHECK (
            tipo IN ('alimentare','imballaggio_primario','detergente_secondario','conto_terzi')
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fornitori DROP CONSTRAINT IF EXISTS fornitori_tipo_values');

        Schema::table('acquisti', function (Blueprint $table) {
            $table->dropColumn('is_conto_terzi');
        });
    }
};
