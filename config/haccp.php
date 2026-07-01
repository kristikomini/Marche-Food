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
];
