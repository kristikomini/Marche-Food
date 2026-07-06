<?php

namespace App\Http\Controllers;

use App\Models\AcquistoRiga;
use App\Models\Produzione;
use App\Models\VenditaRiga;
use App\Services\AllergenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TracciabilitaController extends Controller
{
    public function __construct(private AllergenService $allergeni)
    {
    }

    private const LIMIT_RIGHE    = 50;
    private const LIMIT_PROD     = 20;
    private const LIMIT_VENDITE  = 20;

    public function index()
    {
        return Inertia::render('Tracciabilita', ['risultati' => null, 'query' => '']);
    }

    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return Inertia::render('Tracciabilita', [
                'risultati' => null,
                'query'     => $q,
                'errore'    => 'Inserisci almeno 2 caratteri.',
            ]);
        }

        $term = "%{$q}%";
        // PostgreSQL uses ILIKE; SQLite (tests) uses case-insensitive LIKE.
        $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // ── 1. Forward trace: purchase lots ──────────────────────────────────
        $righeQuery = AcquistoRiga::with([
                'acquisto.fornitore',
                'produzioniMateriePrime.produzione.scheda.prodotto',
            ])
            ->where(function ($query) use ($term, $op) {
                $query->where('lotto', $op, $term)
                      ->orWhere('lotto_esterno', $op, $term)
                      ->orWhere('nome_prodotto', $op, $term);
            })
            ->orderBy('data_in', 'desc');

        $totalRighe    = (clone $righeQuery)->count();
        $righeAcquisto = $righeQuery->limit(self::LIMIT_RIGHE)->get();

        // ── 2. Reverse trace: production lots ────────────────────────────────
        $prodQuery = Produzione::with([
                'scheda.prodotto',
                'materiePrime.materiaPrima',
                'materiePrime.acquistoRiga.acquisto.fornitore',
                'materiePrime.semilavorato.produzione',
            ])
            ->where(function ($query) use ($term, $op) {
                $query->where('lotto_produzione', $op, $term)
                      ->orWhereHas('scheda.prodotto', fn($q) => $q->where('nome', $op, $term));
            })
            ->orderBy('data_produzione', 'desc');

        $totalProduzioni = (clone $prodQuery)->count();
        $produzioni      = $prodQuery->limit(self::LIMIT_PROD)->get();

        // Derived allergen declaration (Reg. UE 1169/2011) per production lot.
        $produzioni->each(function ($p) {
            $p->setAttribute('allergeni', $this->allergeni->forProduzioneLabels($p));
        });

        // ── 3. GAP-D6: sales leg — find finished lots delivered to customers ─
        $venditeQuery = VenditaRiga::with(['vendita.cliente'])
            ->where(function ($query) use ($term, $op) {
                $query->where('lotto', $op, $term)
                      ->orWhere('lotto_esterno', $op, $term)
                      ->orWhere('nome_prodotto', $op, $term);
            })
            ->orderBy('id', 'desc');

        $totalVendite  = (clone $venditeQuery)->count();
        $venditeRighe  = $venditeQuery->limit(self::LIMIT_VENDITE)->get();

        return Inertia::render('Tracciabilita', [
            'risultati' => [
                'righe_acquisto'    => $righeAcquisto,
                'produzioni'        => $produzioni,
                'vendite_righe'     => $venditeRighe,
                'total_righe'       => $totalRighe,
                'total_produzioni'  => $totalProduzioni,
                'total_vendite'     => $totalVendite,
                'limit_righe'       => self::LIMIT_RIGHE,
                'limit_produzioni'  => self::LIMIT_PROD,
                'limit_vendite'     => self::LIMIT_VENDITE,
            ],
            'query' => $q,
        ]);
    }
}
