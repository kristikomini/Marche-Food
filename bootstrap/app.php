<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Traefik reverse proxy so X-Forwarded-Proto: https is respected
        $middleware->trustProxies(at: '*');
        $middleware->web(prepend: [
            ConvertEmptyStringsToNull::class,
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
        // Run the admin role check BEFORE route-model binding, so an unauthorized
        // operator is cleanly redirected instead of leaking a 404 for a missing id.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureAdmin::class,
        );
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
