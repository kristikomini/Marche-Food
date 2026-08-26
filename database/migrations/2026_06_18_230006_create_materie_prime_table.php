<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materie_prime', function (Blueprint $table) {
            $table->id();
            $table->integer('codice')->unique()->nullable();
            $table->string('nome', 200);
            $table->foreignId('um_id')->nullable()->constrained('unita_misura');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materie_prime');
    }
};
