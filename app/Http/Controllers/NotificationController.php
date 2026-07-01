<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications)
    {
        return Inertia::render('Notifiche/Index', [
            'items' => $notifications->forUser($request->user())->values(),
        ]);
    }

    public function dismiss(Request $request, NotificationService $notifications, AppNotification $notification)
    {
        $notifications->dismiss($request->user(), $notification->id);

        return back();
    }

    public function dismissAll(Request $request, NotificationService $notifications)
    {
        $notifications->dismissAll($request->user());

        return back();
    }
}
