<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flussi_produzione', function (Blueprint $table) {
            $table->id();
            $table->integer('numero');
            $table->string('nome', 100);
            $table->string('controllo', 100)->nullable();
            $table->string('misura', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flussi_produzione');
    }
};
