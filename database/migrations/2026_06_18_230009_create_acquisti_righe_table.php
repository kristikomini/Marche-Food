<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisti_righe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acquisto_id')->constrained('acquisti')->cascadeOnDelete();
            $table->foreignId('prodotto_id')->nullable()->constrained('prodotti');
            $table->string('nome_prodotto', 200)->nullable();
            $table->string('um', 10)->nullable();
            $table->decimal('quantita_pz', 10, 3)->nullable();
            $table->decimal('quantita_kg', 10, 3);
            $table->string('lotto', 100)->nullable();
            $table->string('lotto_esterno', 100)->nullable();
            $table->date('scadenza')->nullable();
            $table->date('data_in');
            $table->date('data_out')->nullable();
            $table->string('nota_credito_ref', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisti_righe');
    }
};
