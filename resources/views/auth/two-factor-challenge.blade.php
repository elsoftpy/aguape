<x-layouts.guest title="Verificación en dos pasos">
    <div x-data="{ recovery: false }">
        <h1 class="text-2xl font-bold text-text-primary">Verificación en dos pasos</h1>

        <p class="mt-1 text-sm text-text-secondary" x-cloak>
            <span x-show="! recovery">Ingresá el código de 6 dígitos de tu aplicación de autenticación.</span>
            <span x-show="recovery">Ingresá uno de tus códigos de recuperación.</span>
        </p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-8 space-y-5">
            @csrf

            <template x-if="! recovery">
                <x-input
                    name="code"
                    label="Código de verificación"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                />
            </template>

            <template x-if="recovery">
                <x-input
                    name="recovery_code"
                    label="Código de recuperación"
                    autocomplete="off"
                />
            </template>

            <x-button submit text="Verificar" color="cyan" block />
        </form>

        <button
            type="button"
            x-on:click="recovery = ! recovery"
            class="mt-6 text-center text-xs font-semibold text-text-tertiary underline"
        >
            <span x-show="! recovery">Usar un código de recuperación</span>
            <span x-show="recovery">Usar un código de verificación</span>
        </button>
    </div>
</x-layouts.guest>
