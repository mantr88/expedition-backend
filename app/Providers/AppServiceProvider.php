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

        // Rate limiting (фаза B5): ключ — користувач (guest — IP, до auth
        // такі запити не доходять, але ключ обов'язковий, бо auth:sanctum
        // виконується в pipeline раніше за throttle). Заголовки
        // X-RateLimit-* — контракт для фронту (обробка 429).
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()->id ?? $request->ip())));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()->id ?? $request->ip())));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()->id ?? $request->ip())));
    }
}
