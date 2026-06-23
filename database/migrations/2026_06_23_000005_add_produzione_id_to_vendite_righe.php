<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vendite_righe', 'produzione_id')) {
            return;
        }

        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->foreignId('produzione_id')
                  ->nullable()
                  ->constrained('produzioni')
                  ->nullOnDelete();
            $table->index('produzione_id', 'idx_vendite_righe_produzione');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vendite_righe', 'produzione_id')) {
            return;
        }

        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->dropIndex('idx_vendite_righe_produzione');
            $table->dropForeign(['produzione_id']);
            $table->dropColumn('produzione_id');
        });
    }
};
