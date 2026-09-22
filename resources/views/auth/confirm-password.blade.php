<x-layouts.guest title="Confirmar contraseña">
    <h1 class="text-2xl font-bold text-text-primary">Confirmá tu contraseña</h1>
    <p class="mt-1 text-sm text-text-secondary">Por seguridad, volvé a ingresar tu contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-8 space-y-5">
        @csrf

        <x-password
            name="password"
            label="Contraseña"
            required
            autofocus
            autocomplete="current-password"
        />

        <x-button submit text="Confirmar" color="cyan" block />
    </form>
</x-layouts.guest>