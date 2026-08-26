<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinazione_ingredienti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodotto_id')->constrained('prodotti');
            $table->foreignId('materia_prima_id')->constrained('materie_prime');
            $table->unique(['prodotto_id', 'materia_prima_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinazione_ingredienti');
    }
};
