<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class GenerateNotifications extends Command
{
    protected $signature   = 'notifiche:genera';
    protected $description = 'Genera/aggiorna le notifiche in-app dalle condizioni di dominio (scadenze, recall)';

    public function handle(NotificationService $service): int
    {
        $n = $service->generate();
        $this->info("Notifiche attive: {$n}.");

        return self::SUCCESS;
    }
}
