<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $clienti = [
            [
                'codice_cliente'  => 'CLI001',
                'ragione_sociale' => 'SUPERMERCATI MARCHE S.R.L.',
                'piva'            => 'IT01122334455',
                'indirizzo'       => 'Via Commercio 10, Ancona',
                'email'           => 'ordini@supermercatimarche.it',
                'telefono'        => '071 445566',
                'attivo'          => true,
            ],
            [
                'codice_cliente'  => 'CLI002',
                'ragione_sociale' => 'RISTORANTE DA MARIO S.N.C.',
                'piva'            => 'IT05566778899',
                'indirizzo'       => 'Corso Garibaldi 88, Pesaro',
                'email'           => 'info@damario.it',
                'telefono'        => '0721 667788',
                'attivo'          => true,
            ],
            [
                'codice_cliente'  => 'CLI003',
                'ragione_sociale' => 'PESCHERIA ADRIATICA DI ROSSI',
                'piva'            => 'IT03344556677',
                'indirizzo'       => 'Lungomare 5, Fano',
                'email'           => 'pescheriaadriatica@gmail.com',
                'telefono'        => '0721 889900',
                'attivo'          => true,
            ],
            [
                'codice_cliente'  => 'CLI004',
                'ragione_sociale' => 'CONAD CENTRO NORD S.C.',
                'piva'            => 'IT07788990011',
                'indirizzo'       => 'Via Emilia 200, Rimini',
                'email'           => 'acquisti@conadcentronord.it',
                'telefono'        => '0541 112233',
                'attivo'          => true,
            ],
            [
                'codice_cliente'  => 'CLI005',
                'ragione_sociale' => 'HOTEL BAIA VERDE S.P.A.',
                'piva'            => 'IT09900112233',
                'indirizzo'       => 'Viale del Mare 33, Senigallia',
                'email'           => 'ristorante@hotelbaiaverde.it',
                'telefono'        => '071 998877',
                'attivo'          => false,
            ],
        ];

        foreach ($clienti as $data) {
            Cliente::firstOrCreate(['codice_cliente' => $data['codice_cliente']], $data);
        }
    }
}
