<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schede_produzione', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodotto_id')->constrained('prodotti');
            $table->string('modello', 20);
            $table->integer('revisione')->default(0);
            $table->date('data_revisione');
            $table->boolean('ha_marinatura')->default(false);
            $table->boolean('attiva')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['prodotto_id', 'revisione']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schede_produzione');
    }
};
