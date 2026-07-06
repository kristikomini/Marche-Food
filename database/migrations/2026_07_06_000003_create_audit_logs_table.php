<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit / change log. Unlike the created_by/updated_by columns
 * (which only show who *last* touched a row), this records every create,
 * update, (soft/force) delete and restore of an Auditable model, with the
 * before→after values of each changed field and a human label snapshot that
 * survives even a permanent delete. Rows are never updated or deleted by the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 20); // created | updated | deleted | restored | force_deleted
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->string('etichetta')->nullable(); // display label snapshot at write time
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
