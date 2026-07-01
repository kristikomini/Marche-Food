<?php

namespace App\Console\Commands;

use App\Mail\AlertScadenzeMail;
use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class InviaAlertScadenze extends Command
{
    protected $signature   = 'haccp:alert-scadenze';
    protected $description = 'Invia email alert agli admin per lotti in scadenza e certificati HACCP';

    /**
     * Merge admin emails with the configured extra recipients (deduped).
     * Extracted for unit testing.
     *
     * @param  array<int,string>  $adminEmails
     * @return array<int,string>
     */
    public static function recipients(array $adminEmails): array
    {
        $extra = (array) config('haccp.alert_destinatari_extra', []);

        return array_values(array_unique(array_filter(array_merge($adminEmails, $extra))));
    }

    public function handle(): void
    {
        $giorniLotti = (int) config('haccp.alert_giorni_lotti', 30);
        $giorniCert  = (int) config('haccp.alert_giorni_certificati', 60);

        $today   = now()->toDateString();
        $inLotti = now()->addDays($giorniLotti)->toDateString();
        $inCert  = now()->addDays($giorniCert)->toDateString();

        $inScadenza = AcquistoRiga::with(['acquisto.fornitore:id,ragione_sociale'])
            ->whereNull('data_out')
            ->whereNotNull('scadenza')
            ->whereBetween('scadenza', [$today, $inLotti])
            ->orderBy('scadenza')
            ->get()
            ->toArray();

        $scaduti = AcquistoRiga::with(['acquisto.fornitore:id,ragione_sociale'])
            ->whereNull('data_out')
            ->whereNotNull('scadenza')
            ->where('scadenza', '<', $today)
            ->orderBy('scadenza')
            ->get()
            ->toArray();

        $certificatiInScadenza = Fornitore::where('attivo', true)
            ->where('haccp_certificato', true)
            ->whereNotNull('haccp_scadenza')
            ->where('haccp_scadenza', '<=', $inCert)
            ->orderBy('haccp_scadenza')
            ->get(['id', 'ragione_sociale', 'tipo', 'haccp_scadenza'])
            ->toArray();

        if (empty($inScadenza) && empty($scaduti) && empty($certificatiInScadenza)) {
            $this->info('Nessuna scadenza da segnalare.');
            return;
        }

        $adminEmails = User::where('role', 'admin')->pluck('email')->all();
        $recipients  = self::recipients($adminEmails);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new AlertScadenzeMail(
                $inScadenza,
                $scaduti,
                $certificatiInScadenza,
            ));
        }

        $this->info('Alert scadenze inviato a ' . count($recipients) . ' destinatari.');
    }
}
