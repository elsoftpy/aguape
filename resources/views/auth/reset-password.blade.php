<x-layouts.guest title="Restablecer contraseña">
    <h1 class="text-2xl font-bold text-text-primary">Restablecer contraseña</h1>
    <p class="mt-1 text-sm text-text-secondary">Elegí una nueva contraseña para tu cuenta.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input
            name="email"
            label="Correo electrónico"
            value="{{ old('email', $request->email) }}"
            required
            autofocus
            autocomplete="username"
        />

        <x-password
            name="password"
            label="Nueva contraseña"
            required
            autocomplete="new-password"
        />

        <x-password
            name="password_confirmation"
            label="Confirmar nueva contraseña"
            required
            autocomplete="new-password"
        />

        <div class="flex flex-col items-center">
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.sitekey') }}" data-size="compact"></div>
            @error('g-recaptcha-response')
                <p class="mt-1.5 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-button submit text="Restablecer contraseña" color="cyan" block />
    </form>
</x-layouts.guest>