<x-layouts.guest title="Creá tu contraseña">
    <h1 class="text-2xl font-bold text-text-primary">Creá tu contraseña</h1>

    @if ($enlaceValido)
        <p class="mt-1 text-sm text-text-secondary">
            Elegí una contraseña para ingresar con el usuario <span class="font-medium text-text-primary">{{ $user->name }}</span>.
        </p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8 space-y-5">
            @csrf

            <x-password
                name="password"
                label="Contraseña"
                required
                autofocus
                autocomplete="new-password"
            />

            <x-password
                name="password_confirmation"
                label="Confirmar contraseña"
                required
                autocomplete="new-password"
            />

            <x-button submit text="Crear contraseña" color="cyan" block />
        </form>
    @else
        <p class="mt-1 text-sm text-text-secondary">
            Este enlace venció o ya fue usado. Pedile al administrador del sistema que te reenvíe la invitación.
        </p>

        <x-button :href="route('login')" text="Ir a iniciar sesión" color="cyan" class="mt-8" block />
    @endif
</x-layouts.guest>