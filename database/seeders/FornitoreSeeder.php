<?php

namespace Database\Seeders;

use App\Models\Fornitore;
use Illuminate\Database\Seeder;

class FornitoreSeeder extends Seeder
{
    public function run(): void
    {
        $fornitori = [
            // Alimentari (from FONITORI INPUT sheet)
            [
                'codice'             => 'FOR001',
                'ragione_sociale'    => 'GRASSI MARCO',
                'tipo'               => 'alimentare',
                'piva'               => 'IT01234567890',
                'indirizzo'          => 'Via Roma 12, Ancona',
                'email'              => 'info@grassimarco.it',
                'telefono'           => '071 123456',
                'haccp_certificato'  => true,
                'haccp_scadenza'     => '2027-06-30',
                'certificazioni_note'=> 'Certificazione ISO 22000',
                'attivo'             => true,
            ],
            [
                'codice'             => 'FOR002',
                'ragione_sociale'    => 'EUROMAR S.R.L.',
                'tipo'               => 'alimentare',
                'piva'               => 'IT09876543210',
                'indirizzo'          => 'Via del Mare 45, Fano',
                'email'              => 'commerciale@euromar.it',
                'telefono'           => '0721 987654',
                'haccp_certificato'  => true,
                'haccp_scadenza'     => '2026-12-31',
                'certificazioni_note'=> 'BRC Food Grade A',
                'attivo'             => true,
            ],
            [
                'codice'             => 'FOR003',
                'ragione_sociale'    => 'ELETTROCHIMICA CECI',
                'tipo'               => 'alimentare',
                'piva'               => 'IT05678901234',
                'indirizzo'          => 'Via Industriale 8, Pesaro',
                'email'              => 'ordini@elettrochimica-ceci.it',
                'telefono'           => '0721 456789',
                'haccp_certificato'  => true,
                'haccp_scadenza'     => '2027-03-15',
                'attivo'             => true,
            ],
            [
                'codice'             => 'FOR004',
                'ragione_sociale'    => 'FRATELLI PAGANI S.N.C.',
                'tipo'               => 'alimentare',
                'piva'               => 'IT03456789012',
                'indirizzo'          => 'Contrada Vallone 3, Senigallia',
                'email'              => 'info@fratellipaganì.it',
                'telefono'           => '071 654321',
                'haccp_certificato'  => false,
                'haccp_scadenza'     => null,
                'attivo'             => true,
            ],
            [
                'codice'             => 'FOR005',
                'ragione_sociale'    => 'PESCEINSCATOLA ADRIATICA',
                'tipo'               => 'alimentare',
                'piva'               => 'IT07890123456',
                'indirizzo'          => 'Porto Commerciale, Ancona',
                'email'              => 'acquisti@pesceinscatola.it',
                'telefono'           => '071 555000',
                'haccp_certificato'  => true,
                'haccp_scadenza'     => '2026-09-30',
                'attivo'             => false,
            ],
            // Imballaggi Primari (MOCA)
            [
                'codice'             => 'IMB001',
                'ragione_sociale'    => 'TECNOFOODPACK S.R.L.',
                'tipo'               => 'imballaggio_primario',
                'piva'               => 'IT02345678901',
                'indirizzo'          => 'Via Packaging 22, Bologna',
                'email'              => 'vendite@tecnofoodpack.it',
                'telefono'           => '051 778899',
                'moca_certificato'   => true,
                'moca_numero'        => 'MOCA/2024/IT/00342',
                'attivo'             => true,
            ],
            [
                'codice'             => 'IMB002',
                'ragione_sociale'    => 'VASCHETTE ITALIA S.P.A.',
                'tipo'               => 'imballaggio_primario',
                'piva'               => 'IT04567890123',
                'indirizzo'          => 'Via della Plastica 88, Milano',
                'email'              => 'info@vaschette-italia.it',
                'telefono'           => '02 123456',
                'moca_certificato'   => true,
                'moca_numero'        => 'MOCA/2023/IT/00128',
                'attivo'             => true,
            ],
            // Detergenti / Imballaggi Secondari
            [
                'codice'             => 'DET001',
                'ragione_sociale'    => 'RIVOIRA GAS S.P.A.',
                'tipo'               => 'detergente_secondario',
                'piva'               => 'IT06789012345',
                'indirizzo'          => 'Via Industriale Gas 1, Torino',
                'email'              => 'info@rivoiragas.it',
                'telefono'           => '011 333444',
                'attivo'             => true,
            ],
            [
                'codice'             => 'DET002',
                'ragione_sociale'    => 'PULIZIE PRO S.R.L.',
                'tipo'               => 'detergente_secondario',
                'piva'               => 'IT08901234567',
                'indirizzo'          => 'Via Chimica 5, Jesi',
                'email'              => 'ordini@puliziezpro.it',
                'telefono'           => '0731 234567',
                'attivo'             => true,
            ],
        ];

        foreach ($fornitori as $data) {
            Fornitore::firstOrCreate(['codice' => $data['codice']], $data);
        }
    }
}
