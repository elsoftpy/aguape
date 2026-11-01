<?php

use App\Enums\PermissionsEnum;
use App\Http\Controllers\CrearPasswordController;
use App\Livewire\Pages\Alertas;
use App\Livewire\Pages\Auditoria;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\Destinatarios;
use App\Livewire\Pages\DetalleNodo;
use App\Livewire\Pages\Establecimientos;
use App\Livewire\Pages\Historial;
use App\Livewire\Pages\IntervaloTransmision;
use App\Livewire\Pages\MiCuenta;
use App\Livewire\Pages\Nodos;
use App\Livewire\Pages\Umbrales;
use App\Livewire\Pages\Usuarios;
use App\Livewire\Pages\Variables;
use App\Livewire\Pages\Zonas;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/crear-password/{user}', [CrearPasswordController::class, 'show'])->name('password.create');
    Route::post('/crear-password/{user}', [CrearPasswordController::class, 'store'])->name('password.store');
});

Route::livewire('/dashboard', Dashboard::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerDashboard->value])
    ->name('dashboard');

Route::livewire('/zonas', Zonas::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerDashboard->value])
    ->name('zonas');

Route::livewire('/zonas/nodos/{nodo}', DetalleNodo::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerDashboard->value])
    ->name('zonas.nodo');

Route::livewire('/alertas', Alertas::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerHistorialAlertas->value.'|'.PermissionsEnum::GestionarAlertas->value])
    ->name('alertas');

Route::livewire('/historial', Historial::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerDashboard->value])
    ->name('historial');

Route::livewire('/umbrales', Umbrales::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarUmbrales->value])
    ->name('umbrales');

Route::livewire('/nodos', Nodos::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarNodos->value])
    ->name('nodos');

Route::livewire('/establecimientos', Establecimientos::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarEstablecimientos->value])
    ->name('establecimientos');

Route::livewire('/variables', Variables::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarVariables->value])
    ->name('variables');

Route::livewire('/destinatarios', Destinatarios::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarDestinatarios->value])
    ->name('destinatarios');

Route::livewire('/intervalo-transmision', IntervaloTransmision::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarConfiguracion->value])
    ->name('intervalo-transmision');

Route::livewire('/usuarios', Usuarios::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarUsuarios->value])
    ->name('usuarios');

Route::livewire('/auditoria', Auditoria::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerAuditoria->value])
    ->name('auditoria');

Route::livewire('/mi-cuenta', MiCuenta::class)
    ->middleware(['auth', 'password.confirm'])
    ->name('mi-cuenta');
