<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allergen tracking (EU Reg. 1169/2011). Each raw material declares the
 * allergens it *contains* and those it *may contain* (cross-contact / "tracce").
 * Production-lot allergens are derived from these at read time by AllergenService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materie_prime', function (Blueprint $table) {
            $table->json('allergeni')->nullable();
            $table->json('allergeni_tracce')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('materie_prime', function (Blueprint $table) {
            $table->dropColumn(['allergeni', 'allergeni_tracce']);
        });
    }
};
