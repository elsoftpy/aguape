<x-layouts.guest title="Iniciar sesión">
    <h1 class="text-2xl font-bold text-text-primary">Ingresar</h1>
    <p class="mt-1 text-sm text-text-secondary">Accedé con tu usuario de la estancia.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-success/40 bg-success-dim px-4 py-3 text-sm text-text-primary">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <x-input
            name="name"
            label="Usuario"
            value="{{ old('name') }}"
            placeholder="ej. victor.medina"
            required
            autofocus
            autocomplete="username"
        />

        <x-password
            name="password"
            label="Contraseña"
            required
            autocomplete="current-password"
        />

        <x-checkbox name="remember" label="Recordarme en este dispositivo" color="cyan" />

        {{-- "¿Olvidaste tu contraseña?" queda pendiente: la vista de recuperación
        (Fortify::requestPasswordResetLinkView) todavía no está construida. --}}

        <div class="flex flex-col items-center">
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.sitekey') }}" data-size="compact"></div>
            @error('g-recaptcha-response')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-button submit text="Ingresar" color="cyan" block />
    </form>

    <p class="mt-8 text-center text-xs text-text-tertiary">
        Al ingresar, aceptás los <a href="#" class="font-semibold">Términos y condiciones</a> del sistema.
    </p>
</x-layouts.guest>