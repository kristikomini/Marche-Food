<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reform 2026-07-08 · Fase 4 — Fedeltà fattura/DDT + automazione righe vendita.
 *
 * - clienti: campi anagrafici della fattura (zona, agente, categoria, banca,
 *   codice IVA, valuta, aliquota IVA di default) → popolati una volta, riusati.
 * - vendite: dati trasporto (colli, peso, data trasporto, destinatario diverso).
 * - vendite_righe: collegamento opzionale alla variante prodotto per l'auto-fill
 *   di codice articolo / descrizione / pezzatura.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->string('zona', 50)->nullable();
            $table->string('agente', 100)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->string('banca_appoggio', 150)->nullable();
            $table->string('codice_iva', 20)->nullable();
            $table->string('valuta', 20)->nullable()->default('Euro');
            $table->decimal('aliquota_iva_default', 5, 2)->nullable();
        });

        Schema::table('vendite', function (Blueprint $table) {
            $table->integer('n_colli')->nullable();
            $table->decimal('peso_totale', 10, 3)->nullable();
            $table->date('data_trasporto')->nullable();
            $table->text('destinatario_diverso')->nullable();
        });

        Schema::table('vendite_righe', function (Blueprint $table) {
            // FK applicativa (validata via exists); niente vincolo DB per portabilità SQLite.
            $table->unsignedBigInteger('prodotto_variante_id')->nullable()->after('prodotto_id');
            $table->index('prodotto_variante_id', 'idx_vendite_righe_variante');
        });
    }

    public function down(): void
    {
        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->dropIndex('idx_vendite_righe_variante');
            $table->dropColumn('prodotto_variante_id');
        });

        Schema::table('vendite', function (Blueprint $table) {
            $table->dropColumn(['n_colli', 'peso_totale', 'data_trasporto', 'destinatario_diverso']);
        });

        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['zona', 'agente', 'categoria', 'banca_appoggio', 'codice_iva', 'valuta', 'aliquota_iva_default']);
        });
    }
};
