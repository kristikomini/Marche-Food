<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `deleted_at` column to the operational document tables so an
 * accidental admin delete becomes recoverable (soft-delete) instead of a hard,
 * irreversible DELETE. The line/pivot tables (acquisti_righe, vendite_righe,
 * produzioni_materie_prime, ...) are intentionally NOT soft-deleted: they are
 * preserved untouched when their parent is trashed and are edited via hard
 * delete inside the normal update flow.
 */
return new class extends Migration
{
    private array $tables = [
        'acquisti',
        'vendite',
        'produzioni',
        'bolle_reso',
        'note_credito',
        'lotti_imballaggi_primari',
        'lotti_detergenti',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
