<?php

namespace Tests\Feature;

use App\Console\Commands\InviaAlertScadenze;
use Tests\TestCase;

class AlertRecipientsTest extends TestCase
{
    public function test_merges_admins_with_configured_extras_deduped(): void
    {
        config(['haccp.alert_destinatari_extra' => ['extra@marche.it', 'dup@marche.it']]);

        $recipients = InviaAlertScadenze::recipients(['admin@marche.it', 'dup@marche.it', '']);

        $this->assertSame(['admin@marche.it', 'dup@marche.it', 'extra@marche.it'], $recipients);
    }

    public function test_defaults_are_present(): void
    {
        $this->assertSame(30, (int) config('haccp.alert_giorni_lotti'));
        $this->assertSame(60, (int) config('haccp.alert_giorni_certificati'));
    }

    public function test_no_extras_returns_only_admins(): void
    {
        config(['haccp.alert_destinatari_extra' => []]);

        $this->assertSame(['a@a.it'], InviaAlertScadenze::recipients(['a@a.it']));
    }
}
