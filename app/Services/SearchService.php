<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Fornitore;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use App\Models\Produzione;
use Illuminate\Support\Facades\DB;

/**
 * Cross-entity global search. Returns grouped, link-ready results for the
 * anagrafica plus lot codes across purchases, sales and productions.
 */
class SearchService
{
    private const LIMIT = 10;

    public function search(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['q' => $q, 'gruppi' => []];
        }

        $like = "%{$q}%";
        // PostgreSQL uses ILIKE; SQLite (tests) has case-insensitive LIKE for ASCII.
        $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $gruppi = [];

        $fornitori = Fornitore::where('ragione_sociale', $op, $like)
            ->orWhere('codice', $op, $like)
            ->limit(self::LIMIT)->get(['id', 'ragione_sociale', 'codice']);
        if ($fornitori->count()) {
            $gruppi[] = ['tipo' => 'Fornitori', 'icona' => 'pi-building', 'items' => $fornitori->map(fn ($f) => [
                'label' => $f->ragione_sociale, 'sub' => $f->codice, 'url' => '/fornitori',
            ])];
        }

        $clienti = Cliente::where('ragione_sociale', $op, $like)
            ->orWhere('codice_cliente', $op, $like)
            ->limit(self::LIMIT)->get(['id', 'ragione_sociale', 'codice_cliente']);
        if ($clienti->count()) {
            $gruppi[] = ['tipo' => 'Clienti', 'icona' => 'pi-users', 'items' => $clienti->map(fn ($c) => [
                'label' => $c->ragione_sociale, 'sub' => $c->codice_cliente, 'url' => '/clienti',
            ])];
        }

        $prodotti = Prodotto::where('nome', $op, $like)
            ->orWhere('codice_prodotto', $op, $like)
            ->limit(self::LIMIT)->get(['id', 'nome', 'codice_prodotto']);
        if ($prodotti->count()) {
            $gruppi[] = ['tipo' => 'Prodotti', 'icona' => 'pi-tag', 'items' => $prodotti->map(fn ($p) => [
                'label' => $p->nome, 'sub' => $p->codice_prodotto, 'url' => '/prodotti',
            ])];
        }

        $materie = MateriaPrima::where('nome', $op, $like)
            ->limit(self::LIMIT)->get(['id', 'nome']);
        if ($materie->count()) {
            $gruppi[] = ['tipo' => 'Materie Prime', 'icona' => 'pi-list', 'items' => $materie->map(fn ($m) => [
                'label' => $m->nome, 'sub' => null, 'url' => '/materie-prime',
            ])];
        }

        // Lots: purchases, sales, productions — point to traceability.
        $produzioni = Produzione::with('scheda.prodotto')
            ->where('lotto_produzione', $op, $like)
            ->limit(self::LIMIT)->get();
        if ($produzioni->count()) {
            $gruppi[] = ['tipo' => 'Lotti di produzione', 'icona' => 'pi-cog', 'items' => $produzioni->map(fn ($p) => [
                'label' => $p->lotto_produzione,
                'sub'   => $p->scheda?->prodotto?->nome,
                'url'   => '/tracciabilita?q=' . urlencode($p->lotto_produzione),
            ])];
        }

        $lottiAcquisto = DB::table('acquisti_righe')
            ->join('acquisti', 'acquisti.id', '=', 'acquisti_righe.acquisto_id')
            ->whereNull('acquisti.deleted_at')
            ->where(fn ($w) => $w->where('acquisti_righe.lotto', $op, $like)
                ->orWhere('acquisti_righe.lotto_esterno', $op, $like))
            ->limit(self::LIMIT)
            ->get(['acquisti_righe.lotto', 'acquisti_righe.lotto_esterno', 'acquisti_righe.nome_prodotto']);
        if ($lottiAcquisto->count()) {
            $gruppi[] = ['tipo' => 'Lotti di acquisto', 'icona' => 'pi-download', 'items' => $lottiAcquisto->map(function ($r) {
                $lot = $r->lotto ?: $r->lotto_esterno;
                return ['label' => $lot, 'sub' => $r->nome_prodotto, 'url' => '/tracciabilita?q=' . urlencode($lot)];
            })];
        }

        return ['q' => $q, 'gruppi' => $gruppi];
    }
}
