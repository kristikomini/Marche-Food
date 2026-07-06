<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Surfaces the created_by / updated_by audit columns that the Auditable trait
 * populates across the operational tables. There is no dedicated audit_log
 * table, so this reads the current rows and presents "who last touched what".
 */
class AuditService
{
    /**
     * @var array<string,array{label:string,titolo:string}>
     */
    private array $tables = [
        'acquisti'                 => ['label' => 'numero_documento', 'titolo' => 'Acquisto'],
        'vendite'                  => ['label' => 'numero_documento', 'titolo' => 'Vendita'],
        'produzioni'               => ['label' => 'lotto_produzione', 'titolo' => 'Produzione'],
        'bolle_reso'               => ['label' => 'numero_bolla',     'titolo' => 'Bolla Reso'],
        'note_credito'             => ['label' => 'numero_documento', 'titolo' => 'Nota Credito'],
        'lotti_imballaggi_primari' => ['label' => 'componente',       'titolo' => 'Imballaggio'],
        'lotti_detergenti'         => ['label' => 'componente',       'titolo' => 'Detergente'],
    ];

    /**
     * Most recently touched records across all audited tables.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function recentActivity(int $limit = 100): Collection
    {
        $merged = collect();

        foreach ($this->tables as $table => $meta) {
            $rows = DB::table($table)
                ->leftJoin('users as cu', 'cu.id', '=', "{$table}.created_by")
                ->leftJoin('users as uu', 'uu.id', '=', "{$table}.updated_by")
                ->whereNull("{$table}.deleted_at")
                ->orderByDesc("{$table}.updated_at")
                ->limit($limit)
                ->get([
                    "{$table}.id as id",
                    DB::raw("{$table}.{$meta['label']} as etichetta"),
                    "{$table}.created_at as created_at",
                    "{$table}.updated_at as updated_at",
                    'cu.name as creato_da',
                    'uu.name as modificato_da',
                ]);

            foreach ($rows as $r) {
                $merged->push([
                    'tipo'          => $meta['titolo'],
                    'tabella'       => $table,
                    'id'            => $r->id,
                    'etichetta'     => $r->etichetta,
                    'creato_da'     => $r->creato_da,
                    'modificato_da' => $r->modificato_da,
                    'created_at'    => $r->created_at,
                    'updated_at'    => $r->updated_at,
                ]);
            }
        }

        return $merged
            ->sortByDesc('updated_at')
            ->values()
            ->take($limit);
    }
}
