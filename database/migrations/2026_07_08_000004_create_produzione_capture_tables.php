<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reform 2026-07-08 · Fase 3 — Cattura della produzione reale.
 *
 * Rende la Scheda di Produzione COMPILATA data-driven, registrando per ogni
 * run: N° confezioni per variante, lotti gas (catalogo completo Screen 2),
 * ciclo di lavoro (registrazioni + controllo), e test metal detector.
 */
return new class extends Migration {
    public function up(): void
    {
        // --- Catalogo lotti GAS (Screen 2, come imballaggi/detergenti) ---
        Schema::create('lotti_gas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornitore_id')->constrained('fornitori');
            $table->string('codice_articolo', 50)->nullable();
            $table->string('componente', 200);            // es. "TRESARIS NC30 bombola grande"
            $table->string('um', 10)->nullable();
            $table->decimal('quantita', 10, 3)->nullable();
            $table->string('lotto', 100)->nullable();
            $table->date('scadenza')->nullable();
            $table->string('numero_ddt', 50)->nullable();
            $table->date('data_in');
            $table->date('data_out')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index('fornitore_id', 'idx_gas_fornitore');
        });

        // --- N° confezioni per variante prodotta ---
        Schema::create('produzioni_confezioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->constrained('produzioni')->cascadeOnDelete();
            $table->foreignId('prodotto_variante_id')->constrained('prodotto_varianti');
            $table->integer('n_confezioni')->nullable();
            $table->index('produzione_id', 'idx_prod_conf_produzione');
            $table->index('prodotto_variante_id', 'idx_prod_conf_variante');
        });

        // --- Lotti gas usati nel run ---
        Schema::create('produzioni_gas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->constrained('produzioni')->cascadeOnDelete();
            $table->foreignId('lotto_gas_id')->constrained('lotti_gas')->restrictOnDelete();
            $table->decimal('quantita_usata', 12, 3)->nullable();
            $table->text('note')->nullable();
            $table->index('produzione_id', 'idx_prod_gas_produzione');
            $table->index('lotto_gas_id', 'idx_prod_gas_lotto');
        });

        // --- Ciclo di lavoro compilato (registrazioni + controllo) ---
        Schema::create('produzioni_ciclo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->constrained('produzioni')->cascadeOnDelete();
            $table->foreignId('flusso_id')->nullable()->constrained('flussi_produzione')->nullOnDelete();
            $table->string('nome', 150)->nullable();      // snapshot del nome fase
            $table->string('registrazione_1', 200)->nullable();
            $table->string('registrazione_2', 200)->nullable();
            $table->boolean('controllo')->default(false); // colonna "C" spuntata
            $table->integer('ordine')->default(0);
            $table->index('produzione_id', 'idx_prod_ciclo_produzione');
        });

        // --- Test metal detector (una riga per produzione) ---
        Schema::create('produzioni_metal_detector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->unique()->constrained('produzioni')->cascadeOnDelete();
            $table->string('inizio_conf', 20)->nullable();
            $table->string('fine_conf', 20)->nullable();
            $table->string('campione_1', 3)->nullable();  // OK / KO
            $table->string('campione_2', 3)->nullable();
            $table->string('campione_3', 3)->nullable();
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produzioni_metal_detector');
        Schema::dropIfExists('produzioni_ciclo');
        Schema::dropIfExists('produzioni_gas');
        Schema::dropIfExists('produzioni_confezioni');
        Schema::dropIfExists('lotti_gas');
    }
};
