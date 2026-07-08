<?php

return [
    /*
    | Days ahead to warn about lots approaching their expiry date.
    */
    'alert_giorni_lotti' => (int) env('HACCP_ALERT_GIORNI_LOTTI', 30),

    /*
    | Days ahead to warn about supplier HACCP certificates approaching expiry.
    */
    'alert_giorni_certificati' => (int) env('HACCP_ALERT_GIORNI_CERTIFICATI', 60),

    /*
    | Extra recipients (comma-separated in HACCP_ALERT_EMAILS) that receive the
    | daily expiry digest in addition to all admin users.
    */
    'alert_destinatari_extra' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HACCP_ALERT_EMAILS', ''))
    ))),

    /*
    | Campioni fissi del test metal detector stampati sulla Scheda di Produzione
    | (come da modulo cartaceo M2PO3). Ordine = ordine di stampa.
    */
    'metal_detector_campioni' => [
        ['n' => 1, 'materiale' => 'Materiale Ferroso',     'diametro' => '2,5 mm', 'codice' => '260920'],
        ['n' => 2, 'materiale' => 'Materiale Non Ferroso', 'diametro' => '3,5 mm', 'codice' => '260967'],
        ['n' => 3, 'materiale' => 'Materiale Aisi 316',    'diametro' => '4,5 mm', 'codice' => '260948'],
    ],

    /*
    | Passi standard del ciclo di lavoro proposti nella scheda (numero → nome).
    | Modificabili per scheda tramite flussi_produzione + schede_produzione_flussi.
    */
    'ciclo_lavoro_default' => [
        ['numero' => 1,  'nome' => 'Prelievo prodotti'],
        ['numero' => 3,  'nome' => 'Preparazione ingred. + additivi'],
        ['numero' => 7,  'nome' => 'Porzionatura e confezionamento'],
        ['numero' => 10, 'nome' => 'Immagaz. Preparaz. pallet e Sped.'],
    ],
];
