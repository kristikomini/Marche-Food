<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Inertia\Inertia;

/**
 * Admin-only audit view. Shows the append-only change log (every create/update/
 * delete/restore with before→after values) plus the "who last touched what"
 * summary. Registered behind the `admin` middleware.
 */
class AuditController extends Controller
{
    public function index(AuditService $audit)
    {
        return Inertia::render('Audit/Index', [
            'log'      => $audit->changeLog(300),
            'attivita' => $audit->recentActivity(150)->all(),
        ]);
    }
}
