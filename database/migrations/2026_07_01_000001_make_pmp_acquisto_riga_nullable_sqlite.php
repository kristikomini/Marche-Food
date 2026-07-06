<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 2026_06_23_000006 dropped the NOT NULL on
 * produzioni_materie_prime.acquisto_riga_id **only on PostgreSQL**. On SQLite
 * (the driver used by the automated test suite) the column stayed NOT NULL,
 * which made it impossible to insert a semilavorato-sourced production line
 * (where acquisto_riga_id is NULL and semilavorato_id is set).
 *
 * This migration makes the column nullable on non-PostgreSQL drivers so the
 * internal-source production path is exercisable in tests and any SQLite-backed
 * environment. On PostgreSQL it is a no-op (already nullable).
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            return; // already handled in 2026_06_23_000006
        }

        Schema::table('produzioni_materie_prime', function (Blueprint $table) {
            $table->unsignedBigInteger('acquisto_riga_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as a no-op: restoring NOT NULL could fail if
        // semilavorato-sourced rows (with a NULL acquisto_riga_id) exist.
    }
};
