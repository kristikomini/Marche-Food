<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodotti', function (Blueprint $table) {
            $table->id();
            $table->string('codice_prodotto', 20)->unique();
            $table->string('nome', 200);
            $table->decimal('pezzatura_valore', 10, 3)->nullable();
            $table->string('pezzatura_um', 10)->nullable();
            $table->foreignId('um_id')->nullable()->constrained('unita_misura');
            $table->boolean('attivo')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodotti');
    }
};
