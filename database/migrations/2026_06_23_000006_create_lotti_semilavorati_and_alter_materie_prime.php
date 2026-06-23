<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Create target table first (FK source must reference existing table)
        Schema::create('lotti_semilavorati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->constrained('produzioni')->cascadeOnDelete();
            $table->string('lotto', 100)->unique();
            $table->string('nome_prodotto', 200);
            $table->decimal('quantita_kg', 10, 3);
            $table->date('data_produzione');
            $table->date('data_out')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 2. Drop NOT NULL on acquisto_riga_id via raw SQL.
        //    Schema::table()->change() on FK columns in PostgreSQL can silently drop
        //    the FK constraint itself — raw ALTER is the safe path.
        DB::statement('ALTER TABLE produzioni_materie_prime ALTER COLUMN acquisto_riga_id DROP NOT NULL');

        // 3. Add the semilavorato FK column
        Schema::table('produzioni_materie_prime', function (Blueprint $table) {
            $table->foreignId('semilavorato_id')
                  ->nullable()
                  ->constrained('lotti_semilavorati')
                  ->nullOnDelete();
        });

        // 4. XOR constraint: exactly one of the two sources must be present
        DB::statement('ALTER TABLE produzioni_materie_prime ADD CONSTRAINT source_exactly_one CHECK (
            (acquisto_riga_id IS NOT NULL AND semilavorato_id IS NULL) OR
            (acquisto_riga_id IS NULL     AND semilavorato_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE produzioni_materie_prime DROP CONSTRAINT IF EXISTS source_exactly_one');

        Schema::table('produzioni_materie_prime', function (Blueprint $table) {
            $table->dropForeign(['semilavorato_id']);
            $table->dropColumn('semilavorato_id');
        });

        // Safe to restore NOT NULL because rolling back this migration means no
        // semilavorato rows exist yet, so acquisto_riga_id is populated on every row.
        DB::statement('ALTER TABLE produzioni_materie_prime ALTER COLUMN acquisto_riga_id SET NOT NULL');

        Schema::dropIfExists('lotti_semilavorati');
    }
};
