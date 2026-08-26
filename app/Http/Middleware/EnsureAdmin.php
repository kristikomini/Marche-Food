<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'admin') {
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                abort(403, 'Accesso riservato agli amministratori.');
            }

            return redirect('/')->with('error', 'Accesso riservato agli amministratori.');
        }

        return $next($request);
    }
}
