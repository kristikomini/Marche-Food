<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bolle_reso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendita_riga_id')->constrained('vendite_righe');
            $table->string('numero_bolla', 50)->nullable();
            $table->decimal('quantita_pz', 10, 3)->nullable();
            $table->decimal('quantita_kg', 10, 3);
            $table->date('data_reso');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bolle_reso');
    }
};
