<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fattura immediata DdT: aggiunge i campi economici alle righe di vendita
 * (codice articolo, prezzo unitario, sconti, IVA, importo netto) e alcuni
 * campi di intestazione al documento (condizioni di pagamento, causale del
 * trasporto). I totali (imponibile, imposta, totale) sono calcolati a partire
 * dalle righe e non vengono memorizzati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->string('codice_articolo', 50)->nullable();
            $table->decimal('prezzo_unitario', 12, 4)->nullable();
            $table->decimal('sconto_1', 5, 2)->nullable();
            $table->decimal('sconto_2', 5, 2)->nullable();
            $table->decimal('aliquota_iva', 5, 2)->nullable();
            $table->decimal('importo_netto', 12, 2)->nullable();
        });

        Schema::table('vendite', function (Blueprint $table) {
            $table->string('condizioni_pagamento', 200)->nullable();
            $table->string('causale_trasporto', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->dropColumn([
                'codice_articolo', 'prezzo_unitario', 'sconto_1', 'sconto_2',
                'aliquota_iva', 'importo_netto',
            ]);
        });

        Schema::table('vendite', function (Blueprint $table) {
            $table->dropColumn(['condizioni_pagamento', 'causale_trasporto']);
        });
    }
};
