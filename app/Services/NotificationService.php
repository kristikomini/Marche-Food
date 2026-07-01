<?php

namespace App\Services;

use App\Models\AcquistoRiga;
use App\Models\Fornitore;
use App\Models\Recall;
use Illuminate\Support\Facades\Cache;

/**
 * In-app "alerts center": derives live operational notifications (expiring /
 * expired lots, supplier HACCP certificates, open recalls). These mirror the
 * email digest but are always available via the topbar bell.
 */
class NotificationService
{
    /** Full list of active notification cards. */
    public function current(): array
    {
        $giorniLotti = (int) config('haccp.alert_giorni_lotti', 30);
        $giorniCert  = (int) config('haccp.alert_giorni_certificati', 60);

        $today   = now()->toDateString();
        $inLotti = now()->addDays($giorniLotti)->toDateString();
        $inCert  = now()->addDays($giorniCert)->toDateString();

        $expired = AcquistoRiga::whereNull('data_out')->whereNotNull('scadenza')
            ->where('scadenza', '<', $today)->count();

        $expiring = AcquistoRiga::whereNull('data_out')->whereNotNull('scadenza')
            ->whereBetween('scadenza', [$today, $inLotti])->count();

        $certs = Fornitore::where('attivo', true)->where('haccp_certificato', true)
            ->whereNotNull('haccp_scadenza')->where('haccp_scadenza', '<=', $inCert)->count();

        $openRecalls = Recall::where('stato', '!=', 'chiuso')->count();

        $items = [];
        if ($expired > 0) {
            $items[] = ['livello' => 'danger', 'icona' => 'pi-times-circle', 'titolo' => 'Lotti scaduti in giacenza', 'dettaglio' => "{$expired} lotto/i oltre la data di scadenza", 'url' => '/report'];
        }
        if ($expiring > 0) {
            $items[] = ['livello' => 'warning', 'icona' => 'pi-clock', 'titolo' => "Lotti in scadenza (≤ {$giorniLotti} giorni)", 'dettaglio' => "{$expiring} lotto/i in avvicinamento alla scadenza", 'url' => '/report'];
        }
        if ($certs > 0) {
            $items[] = ['livello' => 'warning', 'icona' => 'pi-verified', 'titolo' => "Certificati HACCP in scadenza (≤ {$giorniCert} giorni)", 'dettaglio' => "{$certs} fornitore/i con certificato in scadenza", 'url' => '/fornitori'];
        }
        if ($openRecalls > 0) {
            $items[] = ['livello' => 'danger', 'icona' => 'pi-megaphone', 'titolo' => 'Recall aperti', 'dettaglio' => "{$openRecalls} recall da gestire", 'url' => '/recall'];
        }

        return $items;
    }

    /** Badge count (cached 60s for the shared Inertia prop). */
    public function count(): int
    {
        return Cache::remember('notifications_count', 60, fn () => count($this->current()));
    }
}
