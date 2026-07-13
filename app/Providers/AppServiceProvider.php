<?php

namespace App\Providers;

use App\Support\Presence;
use App\Support\ReverbPresence;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Presence::class, ReverbPresence::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Контракт API: одиночні ресурси віддаються без обгортки "data";
        // списки формують { data, meta } явно (курсорна пагінація).
        JsonResource::withoutWrapping();

        // Rate limiting (фаза B5): ключ — id користувача, для гостей — IP
        // (на поточних роутах auth:sanctum виконується раніше за throttle,
        // тож ключ завжди user id; IP-fallback — на випадок повторного
        // використання лімітера на неавтентифікованому роуті). Заголовки
        // X-RateLimit-* — контракт для фронту (обробка 429).
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKeyFor($request)));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($this->rateLimitKeyFor($request)));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($this->rateLimitKeyFor($request)));
    }

    /**
     * Ключ rate-limit: id автентифікованого користувача, інакше — IP гостя.
     */
    private function rateLimitKeyFor(Request $request): string
    {
        $user = $request->user();

        return $user !== null ? (string) $user->id : (string) $request->ip();
    }
}
