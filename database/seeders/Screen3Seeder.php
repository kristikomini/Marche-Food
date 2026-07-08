<?php

namespace Database\Seeders;

use App\Models\UnitaMisura;
use App\Models\MateriaPrima;
use App\Models\Prodotto;
use Illuminate\Database\Seeder;

class Screen3Seeder extends Seeder
{
    public function run(): void
    {
        // Unità di misura
        $ums = [
            ['codice' => 'kg',  'descrizione' => 'Chilogrammo', 'tipo' => 'kg'],
            ['codice' => 'g',   'descrizione' => 'Grammo',      'tipo' => 'kg'],
            ['codice' => 'lt',  'descrizione' => 'Litro',       'tipo' => 'lt'],
            ['codice' => 'ml',  'descrizione' => 'Millilitro',  'tipo' => 'lt'],
            ['codice' => 'pz',  'descrizione' => 'Pezzo',       'tipo' => 'n'],
        ];
        foreach ($ums as $u) {
            UnitaMisura::firstOrCreate(['codice' => $u['codice']], $u);
        }

        // Materie prime (ingredienti tipici conserve pesce)
        $materie = [
            ['codice' => 1,  'nome' => 'Tonno pinne gialle'],
            ['codice' => 2,  'nome' => 'Tonno pinna blu'],
            ['codice' => 3,  'nome' => 'Sgombro atlantico'],
            ['codice' => 4,  'nome' => 'Salmone atlantico'],
            ['codice' => 5,  'nome' => 'Olio di oliva'],
            ['codice' => 6,  'nome' => 'Olio di semi di girasole'],
            ['codice' => 7,  'nome' => 'Sale marino'],
            ['codice' => 8,  'nome' => 'Acqua'],
            ['codice' => 9,  'nome' => 'Aceto di vino bianco'],
            ['codice' => 10, 'nome' => 'Peperoncino'],
            ['codice' => 11, 'nome' => 'Aglio'],
            ['codice' => 12, 'nome' => 'Prezzemolo'],
            ['codice' => 13, 'nome' => 'Alloro'],
        ];
        foreach ($materie as $m) {
            MateriaPrima::firstOrCreate(['codice' => $m['codice']], $m);
        }

        // Prodotti finiti (con variante/pezzatura di default)
        $prodotti = [
            ['codice' => 'P001', 'nome' => 'Tonno all\'olio di oliva 800g',   'pezzatura_valore' => 800, 'pezzatura_um' => 'g'],
            ['codice' => 'P002', 'nome' => 'Tonno in salamoia 800g',           'pezzatura_valore' => 800, 'pezzatura_um' => 'g'],
            ['codice' => 'P003', 'nome' => 'Sgombro all\'olio 200g',           'pezzatura_valore' => 200, 'pezzatura_um' => 'g'],
            ['codice' => 'P004', 'nome' => 'Salmone affumicato 100g',          'pezzatura_valore' => 100, 'pezzatura_um' => 'g'],
            ['codice' => 'P005', 'nome' => 'Tonno all\'olio di girasole 800g', 'pezzatura_valore' => 800, 'pezzatura_um' => 'g'],
        ];
        foreach ($prodotti as $p) {
            $prodotto = Prodotto::firstOrCreate(['nome' => $p['nome']], ['attivo' => true]);
            $prodotto->varianti()->firstOrCreate(
                ['codice_prodotto' => $p['codice']],
                ['pezzatura_valore' => $p['pezzatura_valore'], 'pezzatura_um' => $p['pezzatura_um'], 'ordine' => 0, 'attiva' => true]
            );
        }
    }
}
