<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendita_id')->nullable()->constrained('vendite');
            $table->foreignId('bolla_reso_id')->nullable()->constrained('bolle_reso');
            $table->string('numero_documento', 50);
            $table->date('data_documento');
            $table->decimal('importo', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_credito');
    }
};
