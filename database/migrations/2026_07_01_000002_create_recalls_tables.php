<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stateful recall workflow (P-B6): a recall targets a production/lot, moves
 * through aperto → in_corso → chiuso, and tracks per-customer notifications.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('recalls', function (Blueprint $table) {
            $table->id();
            $table->string('lotto', 100);                 // production / lot under recall
            $table->string('prodotto', 200)->nullable();  // descriptive product name
            $table->text('motivo');                       // reason for the recall
            $table->string('stato', 20)->default('aperto'); // aperto | in_corso | chiuso
            $table->date('data_apertura');
            $table->date('data_chiusura')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('stato');
            $table->index('lotto');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE recalls ADD CONSTRAINT recalls_stato_values CHECK (stato IN ('aperto','in_corso','chiuso'))");
        }

        Schema::create('recall_notifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recall_id')->constrained('recalls')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();
            $table->foreignId('vendita_riga_id')->nullable()->constrained('vendite_righe')->nullOnDelete();
            $table->string('documento', 100)->nullable(); // sales document reference
            $table->decimal('quantita_kg', 10, 3)->nullable();
            $table->boolean('notificato')->default(false);
            $table->timestamp('notificato_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('recall_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_notifiche');
        Schema::dropIfExists('recalls');
    }
};
