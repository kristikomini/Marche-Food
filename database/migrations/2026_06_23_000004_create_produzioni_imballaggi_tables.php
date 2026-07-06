<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // GAP-D1: link packaging lots to production runs for full HACCP traceability
        Schema::create('produzioni_imballaggi_primari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')
                  ->constrained('produzioni')
                  ->cascadeOnDelete();
            $table->foreignId('lotto_imballaggio_id')
                  ->constrained('lotti_imballaggi_primari')
                  ->restrictOnDelete();
            $table->decimal('quantita_usata', 12, 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('produzione_id');
            $table->index('lotto_imballaggio_id');
        });

        // GAP-D1: link cleaning/detergent lots to production runs
        Schema::create('produzioni_detergenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produzione_id')
                  ->constrained('produzioni')
                  ->cascadeOnDelete();
            $table->foreignId('lotto_detergente_id')
                  ->constrained('lotti_detergenti')
                  ->restrictOnDelete();
            $table->decimal('quantita_usata', 12, 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('produzione_id');
            $table->index('lotto_detergente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produzioni_detergenti');
        Schema::dropIfExists('produzioni_imballaggi_primari');
    }
};
