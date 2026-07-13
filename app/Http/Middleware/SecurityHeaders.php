<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Захисні заголовки (фаза B5). Контракт: усі відповіді api/*,
 * включно з помилками (401/404/422/429), несуть CSP та
 * anti-sniffing/clickjacking заголовки; web-роути не чіпаємо.
 * Зареєстровано глобально, бо групові middleware не бачать відповіді,
 * відрендерені з винятків у роутері (401 від auth, 404 без маршруту).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
