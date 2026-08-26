<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clienti', function (Blueprint $table) {
            $table->id();
            $table->string('codice_cliente', 20)->unique();
            $table->string('ragione_sociale', 200);
            $table->string('piva', 20)->nullable();
            $table->text('indirizzo')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('attivo')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clienti');
    }
};
