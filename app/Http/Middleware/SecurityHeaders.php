<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline HTTP security headers to every web response.
 *
 * HSTS is only emitted in production (over HTTPS) to avoid pinning
 * localhost/dev to https. The header set is intentionally conservative so it
 * does not break the Inertia/Vite/PrimeVue front-end.
 */
class SecurityHeaders
{
    /**
     * @return array<string,string> The non-HSTS headers, exposed for testing.
     */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'       => '0', // modern browsers: disable legacy auditor
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach (self::headers() as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        // HSTS only when serving over HTTPS in production.
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
