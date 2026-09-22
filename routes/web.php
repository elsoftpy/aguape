<?php

use App\Enums\PermissionsEnum;
use App\Livewire\Pages\Alertas;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\MiCuenta;
use App\Livewire\Pages\Nodos;
use App\Livewire\Pages\Umbrales;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::livewire('/dashboard', Dashboard::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerDashboard->value])
    ->name('dashboard');

Route::livewire('/alertas', Alertas::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::VerHistorialAlertas->value.'|'.PermissionsEnum::GestionarAlertas->value])
    ->name('alertas');

Route::livewire('/umbrales', Umbrales::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarUmbrales->value])
    ->name('umbrales');

Route::livewire('/nodos', Nodos::class)
    ->middleware(['auth', 'permission:'.PermissionsEnum::GestionarNodos->value])
    ->name('nodos');

Route::livewire('/mi-cuenta', MiCuenta::class)
    ->middleware(['auth', 'password.confirm'])
    ->name('mi-cuenta');
