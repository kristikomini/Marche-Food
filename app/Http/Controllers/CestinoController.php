<?php

namespace App\Http\Controllers;

use App\Models\Acquisto;
use App\Models\BollaReso;
use App\Models\LottoDetergente;
use App\Models\LottoGas;
use App\Models\LottoImballaggioPrimario;
use App\Models\NotaCredito;
use App\Models\Produzione;
use App\Models\Vendita;
use Illuminate\Database\QueryException;
use Inertia\Inertia;

/**
 * "Cestino" — recover or permanently remove soft-deleted operational documents.
 * Admin-only (wired in routes/web.php). Mirrors the AuditService table registry.
 */
class CestinoController extends Controller
{
    /**
     * @var array<string,array{model:class-string,label:string,titolo:string}>
     */
    private array $tipi = [
        'acquisti'     => ['model' => Acquisto::class,                 'label' => 'numero_documento', 'titolo' => 'Acquisto'],
        'vendite'      => ['model' => Vendita::class,                  'label' => 'numero_documento', 'titolo' => 'Vendita'],
        'produzioni'   => ['model' => Produzione::class,               'label' => 'lotto_produzione', 'titolo' => 'Produzione'],
        'bolle-reso'   => ['model' => BollaReso::class,                'label' => 'numero_bolla',     'titolo' => 'Bolla di Reso'],
        'note-credito' => ['model' => NotaCredito::class,              'label' => 'numero_documento', 'titolo' => 'Nota di Credito'],
        'imballaggi'   => ['model' => LottoImballaggioPrimario::class, 'label' => 'componente',       'titolo' => 'Lotto Imballaggio'],
        'detergenti'   => ['model' => LottoDetergente::class,          'label' => 'componente',       'titolo' => 'Lotto Detergente'],
        'gas'          => ['model' => LottoGas::class,                 'label' => 'componente',       'titolo' => 'Lotto Gas'],
    ];

    public function index()
    {
        $items = collect();

        foreach ($this->tipi as $tipo => $meta) {
            $model = $meta['model'];

            $rows = $model::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->limit(200)
                ->get(['id', $meta['label'], 'deleted_at']);

            foreach ($rows as $r) {
                $items->push([
                    'tipo'       => $tipo,
                    'titolo'     => $meta['titolo'],
                    'id'         => $r->id,
                    'etichetta'  => $r->{$meta['label']},
                    'deleted_at' => $r->deleted_at,
                ]);
            }
        }

        return Inertia::render('Cestino/Index', [
            'items' => $items->sortByDesc('deleted_at')->values(),
        ]);
    }

    public function restore(string $tipo, int $id)
    {
        $meta  = $this->resolve($tipo);
        $model = $meta['model'];

        $model::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', $meta['titolo'] . ' ripristinato dal cestino.');
    }

    public function forceDelete(string $tipo, int $id)
    {
        $meta  = $this->resolve($tipo);
        $model = $meta['model'];

        $record = $model::onlyTrashed()->findOrFail($id);

        try {
            $record->forceDelete();
        } catch (QueryException $e) {
            return back()->with('error', 'Impossibile eliminare definitivamente: il record è ancora collegato ad altri dati. Elimina prima i documenti collegati.');
        }

        return back()->with('success', $meta['titolo'] . ' eliminato definitivamente.');
    }

    /**
     * @return array{model:class-string,label:string,titolo:string}
     */
    private function resolve(string $tipo): array
    {
        abort_unless(isset($this->tipi[$tipo]), 404);

        return $this->tipi[$tipo];
    }
}
