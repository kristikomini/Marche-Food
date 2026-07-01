<?php

namespace App\Services;

use App\Models\AcquistoRiga;
use App\Models\AppNotification;
use App\Models\Fornitore;
use App\Models\NotificationRead;
use App\Models\Recall;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Database-driven in-app notifications (Epic 5).
 *
 * generate() derives the current set of notifications from domain conditions
 * (expiry, HACCP certs, open recalls), deduplicated by `chiave`; resolved
 * conditions are pruned. Dismissals are per-user; a changed `signature`
 * re-surfaces a previously dismissed notification.
 */
class NotificationService
{
    /** The notifications that should exist right now. */
    public function desired(): array
    {
        $giorniLotti = (int) config('haccp.alert_giorni_lotti', 30);
        $giorniCert  = (int) config('haccp.alert_giorni_certificati', 60);

        $today   = now()->toDateString();
        $inLotti = now()->addDays($giorniLotti)->toDateString();
        $inCert  = now()->addDays($giorniCert)->toDateString();

        $desired = [];

        $expired = AcquistoRiga::whereNull('data_out')->whereNotNull('scadenza')
            ->where('scadenza', '<', $today)->count();
        if ($expired > 0) {
            $desired[] = ['chiave' => 'lotti_scaduti', 'livello' => 'danger',
                'titolo' => 'Lotti scaduti in giacenza', 'messaggio' => "{$expired} lotto/i oltre la scadenza",
                'url' => '/report', 'signature' => (string) $expired];
        }

        $expiring = AcquistoRiga::whereNull('data_out')->whereNotNull('scadenza')
            ->whereBetween('scadenza', [$today, $inLotti])->count();
        if ($expiring > 0) {
            $desired[] = ['chiave' => 'lotti_scadenza', 'livello' => 'warning',
                'titolo' => "Lotti in scadenza (<= {$giorniLotti} giorni)", 'messaggio' => "{$expiring} lotto/i in avvicinamento",
                'url' => '/report', 'signature' => (string) $expiring];
        }

        $certs = Fornitore::where('attivo', true)->where('haccp_certificato', true)
            ->whereNotNull('haccp_scadenza')->where('haccp_scadenza', '<=', $inCert)->count();
        if ($certs > 0) {
            $desired[] = ['chiave' => 'cert_haccp', 'livello' => 'warning',
                'titolo' => 'Certificati HACCP in scadenza', 'messaggio' => "{$certs} fornitore/i",
                'url' => '/fornitori', 'signature' => (string) $certs];
        }

        foreach (Recall::where('stato', '!=', 'chiuso')->get(['id', 'lotto', 'stato']) as $r) {
            $desired[] = ['chiave' => "recall:{$r->id}", 'livello' => 'danger',
                'titolo' => 'Recall in corso', 'messaggio' => "Lotto {$r->lotto} - stato: {$r->stato}",
                'url' => "/recall/{$r->id}", 'signature' => $r->stato];
        }

        return $desired;
    }

    /** Reconcile the DB with the desired set. Returns number of active notifications. */
    public function generate(): int
    {
        $desired = $this->desired();
        $chiavi = array_column($desired, 'chiave');

        foreach ($desired as $d) {
            $existing = AppNotification::where('chiave', $d['chiave'])->first();

            if ($existing) {
                $sigChanged = $existing->signature !== $d['signature'];
                $existing->update($d);
                if ($sigChanged) {
                    NotificationRead::where('notification_id', $existing->id)->delete();
                }
            } else {
                AppNotification::create($d);
            }
        }

        AppNotification::whereNotIn('chiave', $chiavi ?: ['__none__'])->delete();

        return count($desired);
    }

    /** Active (non-dismissed) notifications for a user. */
    public function forUser(User $user): Collection
    {
        return AppNotification::whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('dismissed_at'))
            ->orderByRaw("CASE livello WHEN 'danger' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('dismissed_at'))->count();
    }

    public function dismiss(User $user, int $notificationId): void
    {
        NotificationRead::updateOrCreate(
            ['notification_id' => $notificationId, 'user_id' => $user->id],
            ['dismissed_at' => now()]
        );
    }

    public function dismissAll(User $user): void
    {
        AppNotification::all()->each(fn ($n) => $this->dismiss($user, $n->id));
    }
}
