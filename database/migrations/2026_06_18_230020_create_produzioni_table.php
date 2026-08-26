<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produzioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheda_id')->constrained('schede_produzione');
            $table->string('lotto_produzione', 100)->unique();
            $table->date('data_produzione');
            $table->decimal('quantita_prodotta_kg', 10, 3)->nullable();
            $table->string('operatore', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produzioni');
    }
};
