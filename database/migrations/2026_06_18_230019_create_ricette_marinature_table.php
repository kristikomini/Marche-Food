<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ricette_marinature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('schede_produzione')->cascadeOnDelete();
            $table->foreignId('materia_prima_id')->constrained('materie_prime');
            $table->foreignId('fornitore_id')->nullable()->constrained('fornitori');
            $table->decimal('litri_grammi', 8, 3)->nullable();
            $table->string('um', 10)->nullable();
            $table->integer('ordine')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ricette_marinature');
    }
};
