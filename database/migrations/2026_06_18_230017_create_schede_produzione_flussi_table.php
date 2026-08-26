<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schede_produzione_flussi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('schede_produzione')->cascadeOnDelete();
            $table->foreignId('flusso_id')->constrained('flussi_produzione');
            $table->integer('ordine');
            $table->string('valore_controllo', 100)->nullable();
            $table->integer('tempo_minuti')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schede_produzione_flussi');
    }
};
