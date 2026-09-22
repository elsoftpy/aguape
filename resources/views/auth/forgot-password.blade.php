<x-layouts.guest title="Recuperar contraseña">
    <h1 class="text-2xl font-bold text-text-primary">Recuperar contraseña</h1>
    <p class="mt-1 text-sm text-text-secondary">Ingresá tu correo y te enviamos un enlace para restablecer tu contraseña.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-success/40 bg-success-dim px-4 py-3 text-sm text-text-primary">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf

        <x-input
            name="email"
            label="Correo electrónico"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="email"
        />

        <div class="flex flex-col items-center">
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.sitekey') }}" data-size="compact"></div>
            @error('g-recaptcha-response')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-button submit text="Enviar enlace" color="cyan" block />
    </form>

    <p class="mt-8 text-center text-xs text-text-tertiary">
        <a href="{{ route('login') }}" class="font-semibold">Volver a iniciar sesión</a>
    </p>
</x-layouts.guest>