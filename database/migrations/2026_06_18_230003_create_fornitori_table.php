<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornitori', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 20)->unique()->nullable();
            $table->string('ragione_sociale', 200);
            $table->string('tipo', 30); // alimentare | imballaggio_primario | detergente_secondario
            $table->string('piva', 20)->nullable();
            $table->text('indirizzo')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('haccp_certificato')->default(false);
            $table->date('haccp_scadenza')->nullable();
            $table->text('certificazioni_note')->nullable();
            $table->boolean('moca_certificato')->default(false);
            $table->string('moca_numero', 50)->nullable();
            $table->boolean('attivo')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornitori');
    }
};
