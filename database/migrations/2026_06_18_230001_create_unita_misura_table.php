<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unita_misura', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 20)->unique();
            $table->string('descrizione', 100)->nullable();
            $table->string('tipo', 5)->nullable(); // kg, lt, n
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unita_misura');
    }
};
