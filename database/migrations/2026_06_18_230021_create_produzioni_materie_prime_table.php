<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produzioni_materie_prime', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')->constrained('produzioni')->cascadeOnDelete();
            $table->foreignId('acquisto_riga_id')->constrained('acquisti_righe');
            $table->foreignId('materia_prima_id')->constrained('materie_prime');
            $table->decimal('quantita_kg', 10, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produzioni_materie_prime');
    }
};
