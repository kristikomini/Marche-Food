<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reform 2026-07-08 · Fase 2 — Template della Scheda di Produzione.
 *
 * La scheda reale ha, oltre alle materie prime (ricette) e al ciclo di lavoro
 * (schede_produzione_flussi), due sezioni finora hard-coded nel PDF:
 *   - IMBALLAGGI PRIMARI (Vaschetta/Film per pezzatura)
 *   - GAS (es. TRESARIS NC30 · LINDE GAS)
 * Le modelliamo come righe-template della scheda; il lotto reale viene poi
 * registrato per-produzione in Fase 3.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('schede_imballaggi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('schede_produzione')->cascadeOnDelete();
            $table->string('componente', 200);
            $table->foreignId('prodotto_variante_id')->nullable()->constrained('prodotto_varianti')->nullOnDelete();
            $table->foreignId('fornitore_id')->nullable()->constrained('fornitori')->nullOnDelete();
            $table->integer('ordine')->default(0);
            $table->index('scheda_id', 'idx_schede_imb_scheda');
        });

        Schema::create('schede_gas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('schede_produzione')->cascadeOnDelete();
            $table->string('nome', 200);
            $table->foreignId('fornitore_id')->nullable()->constrained('fornitori')->nullOnDelete();
            $table->integer('ordine')->default(0);
            $table->index('scheda_id', 'idx_schede_gas_scheda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schede_gas');
        Schema::dropIfExists('schede_imballaggi');
    }
};
