<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database-driven in-app notifications (Epic 5). Notifications are generated
 * from domain conditions (expiry, recalls) and deduplicated by `chiave`.
 * Per-user dismissals are tracked in `notification_reads`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('chiave')->unique();      // dedup key for the condition
            $table->string('livello', 20)->default('info'); // info | warning | danger
            $table->string('titolo', 200);
            $table->string('messaggio', 500)->nullable();
            $table->string('url', 300)->nullable();
            $table->string('signature', 100)->nullable(); // changes → re-surface after dismissal
            $table->timestamps();
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('app_notifications');
    }
};
