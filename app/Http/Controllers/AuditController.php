<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Inertia\Inertia;

/**
 * Admin-only view of "who last created/modified what" across operational
 * records. Registered behind the `admin` middleware.
 */
class AuditController extends Controller
{
    public function index(AuditService $audit)
    {
        return Inertia::render('Audit/Index', [
            'attivita' => $audit->recentActivity(150)->all(),
        ]);
    }
}
