<?php

namespace App\Services;

use App\Models\Produzione;

/**
 * Allergen matrix for EU Reg. 1169/2011 (14 allergens). Raw materials declare
 * what they *contain* and *may contain*; a production lot's allergens are the
 * union of its ingredients, recursing through semi-finished (semilavorato)
 * ingredients so a sub-recipe's allergens propagate up to the parent lot.
 */
class AllergenService
{
    /** @var array<string,string> code => Italian label */
    public const EU_ALLERGENS = [
        'cereali_glutine' => 'Cereali contenenti glutine',
        'crostacei'       => 'Crostacei',
        'uova'            => 'Uova',
        'pesce'           => 'Pesce',
        'arachidi'        => 'Arachidi',
        'soia'            => 'Soia',
        'latte'           => 'Latte e lattosio',
        'frutta_guscio'   => 'Frutta a guscio',
        'sedano'          => 'Sedano',
        'senape'          => 'Senape',
        'sesamo'          => 'Semi di sesamo',
        'solfiti'         => 'Anidride solforosa e solfiti',
        'lupini'          => 'Lupini',
        'molluschi'       => 'Molluschi',
    ];

    /**
     * Options list for front-end selects: [{code, label}, ...].
     *
     * @return array<int,array{code:string,label:string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::EU_ALLERGENS as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }

    /**
     * Map allergen codes to their Italian labels, ignoring unknown codes.
     *
     * @param  array<int,string>  $codes
     * @return array<int,string>
     */
    public function labels(array $codes): array
    {
        $out = [];
        foreach ($codes as $c) {
            if (isset(self::EU_ALLERGENS[$c])) {
                $out[] = self::EU_ALLERGENS[$c];
            }
        }

        return $out;
    }

    /**
     * Derived allergen set for a production lot.
     *
     * @param  array<int,int>  $visited  guards against semilavorato cycles
     * @return array{contiene:array<int,string>,tracce:array<int,string>}
     */
    public function forProduzione(Produzione $produzione, array &$visited = []): array
    {
        if (in_array($produzione->id, $visited, true)) {
            return ['contiene' => [], 'tracce' => []];
        }
        $visited[] = $produzione->id;

        $produzione->loadMissing([
            'materiePrime.materiaPrima',
            'materiePrime.semilavorato.produzione',
        ]);

        $contiene = [];
        $tracce   = [];

        foreach ($produzione->materiePrime as $mp) {
            if ($mp->materiaPrima) {
                $contiene = array_merge($contiene, $mp->materiaPrima->allergeni ?? []);
                $tracce   = array_merge($tracce, $mp->materiaPrima->allergeni_tracce ?? []);
            }

            if ($mp->semilavorato && $mp->semilavorato->produzione) {
                $sub = $this->forProduzione($mp->semilavorato->produzione, $visited);
                $contiene = array_merge($contiene, $sub['contiene']);
                $tracce   = array_merge($tracce, $sub['tracce']);
            }
        }

        $contiene = array_values(array_intersect(array_keys(self::EU_ALLERGENS), array_unique($contiene)));
        // A "may contain" is dropped once the allergen is a declared "contains".
        $tracce = array_values(array_intersect(
            array_keys(self::EU_ALLERGENS),
            array_diff(array_unique($tracce), $contiene)
        ));

        return ['contiene' => $contiene, 'tracce' => $tracce];
    }

    /**
     * Same as forProduzione but resolved to Italian labels for display.
     *
     * @return array{contiene:array<int,string>,tracce:array<int,string>}
     */
    public function forProduzioneLabels(Produzione $produzione): array
    {
        $codes = $this->forProduzione($produzione);

        return [
            'contiene' => $this->labels($codes['contiene']),
            'tracce'   => $this->labels($codes['tracce']),
        ];
    }
}
