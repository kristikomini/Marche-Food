<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionally links an incoming purchase lot to a raw material (materia prima).
 * This is what lets allergens (and, later, other master-data attributes) flow
 * from the anagrafica onto received lots — closing the gap where allergens only
 * existed on production lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acquisti_righe', function (Blueprint $table) {
            $table->foreignId('materia_prima_id')->nullable()->after('prodotto_id')
                ->constrained('materie_prime')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acquisti_righe', function (Blueprint $table) {
            $table->dropConstrainedForeignId('materia_prima_id');
        });
    }
};
