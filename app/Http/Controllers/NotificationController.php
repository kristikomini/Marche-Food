<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(NotificationService $notifications)
    {
        return Inertia::render('Notifiche/Index', [
            'items' => $notifications->current(),
        ]);
    }
}
