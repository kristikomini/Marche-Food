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

    public function handle(): void
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();
        $in60  = now()->addDays(60)->toDateString();

        $inScadenza = AcquistoRiga::with(['acquisto.fornitore:id,ragione_sociale'])
            ->whereNull('data_out')
            ->whereNotNull('scadenza')
            ->whereBetween('scadenza', [$today, $in30])
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
            ->where('haccp_scadenza', '<=', $in60)
            ->orderBy('haccp_scadenza')
            ->get(['id', 'ragione_sociale', 'tipo', 'haccp_scadenza'])
            ->toArray();

        if (empty($inScadenza) && empty($scaduti) && empty($certificatiInScadenza)) {
            $this->info('Nessuna scadenza da segnalare.');
            return;
        }

        $admins = User::where('role', 'admin')->pluck('email');

        foreach ($admins as $email) {
            Mail::to($email)->send(new AlertScadenzeMail(
                $inScadenza,
                $scaduti,
                $certificatiInScadenza,
            ));
        }

        $this->info("Alert scadenze inviato a {$admins->count()} admin.");
    }
}
