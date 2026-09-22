<?php

namespace App\Providers;

use Illuminate\Support\Number;
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
        // Coma decimal / punto de miles (es-PY), no solo la traducción de
        // texto (APP_LOCALE ya se usa para eso desde RF10) — sin esto,
        // Number::format() usa el default en-US de Laravel.
        Number::useLocale(config('app.locale'));
    }
}
