<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->foreignId('acquisto_riga_id')
                  ->nullable()
                  ->constrained('acquisti_righe')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendite_righe', function (Blueprint $table) {
            $table->dropForeign(['acquisto_riga_id']);
            $table->dropColumn('acquisto_riga_id');
        });
    }
};
