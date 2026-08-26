<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotti_detergenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornitore_id')->constrained('fornitori');
            $table->string('codice_articolo', 50)->nullable();
            $table->string('componente', 200);
            $table->string('um', 10)->nullable();
            $table->decimal('quantita', 10, 3)->nullable();
            $table->string('lotto', 100)->nullable();
            $table->date('scadenza')->nullable();
            $table->string('numero_ddt', 50)->nullable();
            $table->date('data_in');
            $table->date('data_out')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotti_detergenti');
    }
};
