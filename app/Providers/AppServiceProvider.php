<?php

namespace App\Providers;

use App\Enums\PermissionsEnum;
use App\Models\User;
use App\Notifications\AlertaGenerada;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
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
        // Nube de solo lectura. Va antes que el Gate::before de laravel-permission, que si no gana.
        $this->callAfterResolving(GateContract::class, function (GateContract $gate): void {
            $gate->before(function (User $user, string $permiso): ?bool {
                if (config('app.rol') !== 'nube') {
                    return null;
                }

                if ($permiso === PermissionsEnum::VerHistorialAlertas->value && $user->checkPermissionTo(PermissionsEnum::GestionarAlertas->value)) {
                    return true;
                }

                return in_array($permiso, [PermissionsEnum::VerDashboard->value, PermissionsEnum::VerHistorialAlertas->value], true) ? null : false;
            });
        });
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
