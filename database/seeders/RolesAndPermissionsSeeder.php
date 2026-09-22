<?php

namespace Database\Seeders;

use App\Enums\PermissionsEnum;
use App\Enums\RolesEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionsEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(RolesEnum::Administrador->value, 'web')->syncPermissions(Permission::all());

        Role::findOrCreate(RolesEnum::Operador->value, 'web')->syncPermissions([
            PermissionsEnum::VerDashboard->value,
            PermissionsEnum::GestionarUmbrales->value,
            PermissionsEnum::GestionarNodos->value,
            PermissionsEnum::GestionarAlertas->value,
        ]);

        Role::findOrCreate(RolesEnum::Visor->value, 'web')->syncPermissions([
            PermissionsEnum::VerDashboard->value,
            PermissionsEnum::VerHistorialAlertas->value,
        ]);
    }
}
