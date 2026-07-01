<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Readiness probe for Coolify / Traefik.
 *
 * Unlike Laravel's built-in `/up` liveness route, this endpoint also verifies
 * that the database is reachable, returning HTTP 503 when it is not so the
 * orchestrator does not route traffic to an instance that cannot serve data.
 */
class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = ['app' => 'ok'];
        $healthy = true;

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'unavailable';
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'time'   => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
