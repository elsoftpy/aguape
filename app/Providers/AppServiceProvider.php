<?php

namespace App\Providers;

use App\Notifications\AlertaGenerada;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        Livewire::addPersistentMiddleware([
            RequirePassword::class,
        ]);

        Event::listen(function (NotificationSent $event): void {
            if ($event->notification instanceof AlertaGenerada) {
                $event->notification->alerta->update(['notificada_en' => now()]);
            }
        });
    }
}
