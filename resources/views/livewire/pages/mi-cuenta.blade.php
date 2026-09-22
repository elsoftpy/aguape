<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Mi cuenta" />

    <div class="max-w-xl space-y-6 px-6 py-6 md:px-8">
        @php
            // 'two-factor-authentication-disabled' no está acá: desactivar ya
            // no pasa por la respuesta de Fortify (ver MiCuenta::desactivar()),
            // el aviso de esa acción es un toast de TallStackUI, no este flash.
            $mensajes = [
                'two-factor-authentication-enabled' => ['success', 'Escaneá el código QR y confirmá con un código de tu app de autenticación.'],
                'two-factor-authentication-confirmed' => ['success', 'Verificación en dos pasos activada.'],
                'recovery-codes-generated' => ['success', 'Se generaron nuevos códigos de recuperación.'],
            ];
        @endphp

        @if (session('status') && isset($mensajes[session('status')]))
            <div class="rounded-lg border border-success/40 bg-success-dim px-4 py-3 text-sm text-text-primary">
                {{ $mensajes[session('status')][1] }}
            </div>
        @endif

        <div class="rounded-[10px] border border-border bg-panel p-5">
            <h2 class="text-sm font-semibold text-text-primary">Datos de la cuenta</h2>

            <div class="mt-3 divide-y divide-border">
                <div class="flex items-center justify-between py-2.5">
                    <span class="text-xs tracking-wide text-text-tertiary uppercase">Usuario</span>
                    <span class="font-mono text-sm text-text-primary">{{ $this->usuario->name }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-xs tracking-wide text-text-tertiary uppercase">Correo</span>
                    <span class="font-mono text-sm text-text-primary">{{ $this->usuario->email }}</span>
                </div>

                <div class="flex items-center justify-between py-2.5">
                    <span class="text-xs tracking-wide text-text-tertiary uppercase">Rol</span>
                    <span class="text-sm font-medium text-text-primary">{{ $this->usuario->roles->first()?->name ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-border bg-panel p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-text-primary">Verificación en dos pasos</h2>

                @if ($this->usuario->hasEnabledTwoFactorAuthentication())
                    <span class="rounded-full bg-success-dim px-2.5 py-1 text-xs font-medium text-success">Activa</span>
                @else
                    <span class="rounded-full bg-danger-dim px-2.5 py-1 text-xs font-medium text-danger">Inactiva</span>
                @endif
            </div>

            @if (! $this->usuario->two_factor_secret)
                {{-- Ni activada ni pendiente de confirmar --}}
                <p class="mt-2 text-sm text-text-secondary">
                    Agregá una capa extra de seguridad: además de tu contraseña, vas a necesitar un código de tu app de autenticación (Google Authenticator, Authy, etc.) para iniciar sesión.
                </p>

                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <x-button submit text="Activar verificación en dos pasos" color="cyan" />
                </form>
            @elseif (! $this->usuario->two_factor_confirmed_at)
                {{-- Activada pero pendiente de confirmar: mostrar QR + form de confirmación --}}
                <p class="mt-2 text-sm text-text-secondary">
                    Escaneá este código con tu app de autenticación y después ingresá el código de 6 dígitos que te muestra.
                </p>

                <div class="mt-4 inline-block rounded-lg bg-white p-3">
                    {!! $this->usuario->twoFactorQrCodeSvg() !!}
                </div>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <x-input name="code" label="Código de verificación" inputmode="numeric" autocomplete="one-time-code" class="w-40" />
                    <x-button submit text="Confirmar" color="cyan" />
                </form>

                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-text-tertiary underline">Cancelar activación</button>
                </form>
            @else
                {{-- Confirmada: mostrar códigos de recuperación + desactivar --}}
                <p class="mt-2 text-sm text-text-secondary">
                    Guardá estos códigos de recuperación en un lugar seguro. Cada uno se puede usar una sola vez para entrar si perdés acceso a tu app de autenticación.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-surface p-4 font-mono text-xs text-text-secondary">
                    @foreach ($this->usuario->recoveryCodes() as $codigo)
                        <span wire:key="codigo-{{ $loop->index }}">{{ $codigo }}</span>
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <x-button submit text="Generar nuevos códigos" />
                    </form>

                    <x-button text="Desactivar" color="red" outline wire:click="confirmarDesactivacion" />
                </div>
            @endif
        </div>
    </div>
</div>