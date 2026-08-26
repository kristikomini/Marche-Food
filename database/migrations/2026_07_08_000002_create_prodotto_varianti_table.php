<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reform 2026-07-08 · Fase 1 — Varianti pezzatura prodotto.
 *
 * La scheda reale elenca più righe CODICE / PEZZATURA / N° CONFEZIONI per lo
 * stesso prodotto (es. Acciughe salate in olio → 059/gr200 e 397/kg1). Il
 * modello attuale (un solo codice + una sola pezzatura su `prodotti`) non lo
 * rappresenta. Introduciamo `prodotto_varianti` e, per decisione confermata,
 * MIGRIAMO i dati esistenti in una variante e RIMUOVIAMO subito le colonne
 * legacy da `prodotti`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Nuova tabella varianti.
        Schema::create('prodotto_varianti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodotto_id')->constrained('prodotti')->cascadeOnDelete();
            $table->string('codice_prodotto', 20)->unique();
            $table->decimal('pezzatura_valore', 10, 3)->nullable();
            $table->string('pezzatura_um', 10)->nullable();
            $table->foreignId('um_id')->nullable()->constrained('unita_misura');
            $table->string('descrizione', 200)->nullable();
            $table->integer('ordine')->default(0);
            $table->boolean('attiva')->default(true);
            $table->timestamps();
            $table->index('prodotto_id', 'idx_varianti_prodotto');
            $table->index('um_id', 'idx_varianti_um');
        });

        // 2) Data-migration: una variante di default per ogni prodotto esistente.
        $now = now();
        foreach (DB::table('prodotti')->orderBy('id')->get() as $p) {
            DB::table('prodotto_varianti')->insert([
                'prodotto_id' => $p->id,
                'codice_prodotto' => $p->codice_prodotto,
                'pezzatura_valore' => $p->pezzatura_valore ?? null,
                'pezzatura_um' => $p->pezzatura_um ?? null,
                'um_id' => $p->um_id ?? null,
                'descrizione' => null,
                'ordine' => 0,
                'attiva' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3) Rimozione colonne legacy da `prodotti`.
        //    La FK su um_id va rimossa PRIMA di droppare la colonna, su OGNI driver.
        //    Il commento precedente diceva "su SQLite è no-op": non è vero. SQLite
        //    fallisce con `unknown column "um_id" in foreign key definition`, ed è per
        //    questo che l'intera suite di test era rossa mentre la produzione (pgsql)
        //    funzionava.
        if (Schema::hasColumn('prodotti', 'um_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE prodotti DROP CONSTRAINT IF EXISTS prodotti_um_id_foreign');
            } else {
                Schema::table('prodotti', function (Blueprint $table) {
                    $table->dropForeign(['um_id']);
                });
            }
        }
        DB::statement('DROP INDEX IF EXISTS idx_prodotti_codice');
        // `codice_prodotto` was declared with ->unique() in create_prodotti_table, so
        // Laravel also built `prodotti_codice_prodotto_unique`. Dropping only
        // idx_prodotti_codice — a name that was never actually created — left that
        // unique index in place, and SQLite refuses to drop a column an index still
        // references, which broke every test that runs migrations. PostgreSQL drops
        // dependent indexes implicitly, which is why production never surfaced this.
        DB::statement('DROP INDEX IF EXISTS prodotti_codice_prodotto_unique');

        Schema::table('prodotti', function (Blueprint $table) {
            $table->dropColumn(['codice_prodotto', 'pezzatura_valore', 'pezzatura_um', 'um_id']);
        });
    }

    public function down(): void
    {
        // Ripristino colonne legacy su `prodotti`.
        Schema::table('prodotti', function (Blueprint $table) {
            $table->string('codice_prodotto', 20)->nullable();
            $table->decimal('pezzatura_valore', 10, 3)->nullable();
            $table->string('pezzatura_um', 10)->nullable();
            $table->foreignId('um_id')->nullable()->constrained('unita_misura');
        });

        // Ricopia dalla prima variante (ordine minore) di ciascun prodotto.
        foreach (DB::table('prodotti')->orderBy('id')->get() as $p) {
            $v = DB::table('prodotto_varianti')
                ->where('prodotto_id', $p->id)
                ->orderBy('ordine')->orderBy('id')
                ->first();
            if ($v) {
                DB::table('prodotti')->where('id', $p->id)->update([
                    'codice_prodotto' => $v->codice_prodotto,
                    'pezzatura_valore' => $v->pezzatura_valore,
                    'pezzatura_um' => $v->pezzatura_um,
                    'um_id' => $v->um_id,
                ]);
            }
        }

        DB::statement('CREATE INDEX IF NOT EXISTS idx_prodotti_codice ON prodotti(codice_prodotto)');

        Schema::dropIfExists('prodotto_varianti');
    }
};
