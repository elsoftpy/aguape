@php
    $navItems = collect([
        ['label' => 'Panel general', 'icon' => 'squares-2x2', 'route' => 'dashboard', 'permission' => null, 'group' => 'main'],
        ['label' => 'Zonas / potreros', 'icon' => 'map', 'route' => 'zonas', 'permission' => \App\Enums\PermissionsEnum::VerDashboard, 'group' => 'main'],
         ['label' => 'Historial', 'icon' => 'clock', 'route' => 'historial', 'permission' => \App\Enums\PermissionsEnum::VerDashboard, 'group' => 'main'],
        ['label' => 'ITH por cortes', 'icon' => 'sun', 'route' => 'ith', 'permission' => \App\Enums\PermissionsEnum::VerDashboard, 'group' => 'main'],
        ['label' => 'Alertas', 'icon' => 'bell-alert', 'route' => 'alertas', 'permission' => [\App\Enums\PermissionsEnum::VerHistorialAlertas, \App\Enums\PermissionsEnum::GestionarAlertas], 'group' => 'main'],
        /* ['label' => 'Reportes', 'icon' => 'chart-bar', 'route' => null, 'permission' => null, 'group' => 'main'], */
        ['label' => 'Nodos LoRa', 'icon' => 'cpu-chip', 'route' => 'nodos', 'permission' => \App\Enums\PermissionsEnum::GestionarNodos, 'group' => 'settings'],
        ['label' => 'Intervalo de transmisión', 'icon' => 'signal', 'route' => 'intervalo-transmision', 'permission' => \App\Enums\PermissionsEnum::GestionarConfiguracion, 'group' => 'settings'],
        ['label' => 'Configuración de umbrales', 'icon' => 'adjustments-horizontal', 'route' => 'umbrales', 'permission' => \App\Enums\PermissionsEnum::GestionarUmbrales, 'group' => 'settings'],
        ['label' => 'Establecimientos', 'icon' => 'home-modern', 'route' => 'establecimientos', 'permission' => \App\Enums\PermissionsEnum::GestionarEstablecimientos, 'group' => 'admin'],
        ['label' => 'Variables monitoreadas', 'icon' => 'variable', 'route' => 'variables', 'permission' => \App\Enums\PermissionsEnum::GestionarVariables, 'group' => 'admin'],
        ['label' => 'Destinatarios de alertas', 'icon' => 'envelope', 'route' => 'destinatarios', 'permission' => \App\Enums\PermissionsEnum::GestionarDestinatarios, 'group' => 'admin'],
        ['label' => 'Usuarios y roles', 'icon' => 'users', 'route' => 'usuarios', 'permission' => \App\Enums\PermissionsEnum::GestionarUsuarios, 'group' => 'admin'],
        ['label' => 'Auditoría', 'icon' => 'shield-check', 'route' => 'auditoria', 'permission' => \App\Enums\PermissionsEnum::VerAuditoria, 'group' => 'admin'],
        /* ['label' => 'Pasarela de pago', 'icon' => 'credit-card', 'route' => null, 'permission' => \App\Enums\PermissionsEnum::GestionarFacturacion, 'group' => 'admin'], */
        ['label' => 'Mi cuenta', 'icon' => 'user-circle', 'route' => 'mi-cuenta', 'permission' => null, 'group' => 'account'],
    ])
        ->filter(function (array $item) {
            if ($item['permission'] === null) {
                return true;
            }

            $permisos = is_array($item['permission']) ? $item['permission'] : [$item['permission']];

            return collect($permisos)->contains(fn ($permiso) => auth()->user()->can($permiso->value));
        })
        ->values();
@endphp

<div
    x-show="sidebarOpen"
    x-cloak
    x-on:click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-black/50 md:hidden"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-[216px] shrink-0 -translate-x-full flex-col border-r border-border bg-sidebar transition-transform duration-200 md:static md:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex items-center gap-3 px-4 py-5">
        <div class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-success to-accent text-sm font-bold text-accent-text">
            AG
        </div>
        <div class="leading-tight">
            <p class="text-sm font-bold text-text-primary">Aguape</p>
            <p class="text-[10px] tracking-wide text-text-tertiary uppercase">Monitoreo IoT</p>
        </div>
    </div>

    <nav class="flex-1 space-y-1 px-3">
        @foreach ($navItems as $item)
            @if ($loop->index > 0 && $item['group'] !== $navItems[$loop->index - 1]['group'])
                <div class="my-2 border-t border-border"></div>
            @endif

            @php $active = $item['route'] && request()->routeIs($item['route'], $item['route'].'.*'); @endphp

            @if ($item['route'])
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    x-on:click="sidebarOpen = false"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                        {{ $active ? 'bg-accent/15 text-accent' : 'text-text-secondary hover:bg-surface hover:text-text-primary' }}"
                >
                    <x-icon :name="$item['icon']" sm />
                    {{ $item['label'] }}
                </a>
            @else
                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-tertiary/60">
                    <x-icon :name="$item['icon']" sm />
                    {{ $item['label'] }}
                </span>
            @endif
        @endforeach
    </nav>

    <x-layouts.sidebar-user />
</aside>