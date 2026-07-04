<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
