<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clienti');
            $table->string('numero_documento', 50);
            $table->date('data_documento');
            $table->string('tipo_documento', 5); // DDT | FI | NC
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendite');
    }
};
