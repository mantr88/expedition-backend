<?php

namespace App\Providers;

use App\Support\Presence;
use App\Support\ReverbPresence;
use Illuminate\Http\Resources\Json\JsonResource;
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
    }
}
