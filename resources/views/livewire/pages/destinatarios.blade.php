<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Destinatarios de alertas" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Correos que reciben cada alerta generada. Si la lista está vacía, las alertas se envían a los usuarios que pueden gestionar alertas.
        </p>

        <form wire:submit="agregar" class="flex max-w-xl flex-wrap items-start gap-3">
            <div class="min-w-[240px] flex-1">
                <x-input wire:model="email" type="email" placeholder="correo@ejemplo.com" />
            </div>

            <x-button type="submit" text="Agregar" icon="plus" />
        </form>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->destinatarios as $destinatario)
                <div wire:key="destinatario-{{ $destinatario->id }}" class="flex items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon name="envelope" sm />
                        </span>

                        <p class="truncate text-sm font-medium text-text-primary">{{ $destinatario->email }}</p>
                    </div>

                    <button
                        type="button"
                        wire:click="confirmarRetiro({{ $destinatario->id }})"
                        class="flex shrink-0 items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-danger transition hover:bg-danger/10"
                    >
                        <x-icon name="trash" class="size-3.5" />
                        Retirar
                    </button>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-text-tertiary">
                    <p class="text-center">No hay destinatarios configurados.</p>

                    @if ($this->destinatariosPorDefecto->isNotEmpty())
                        <p class="mt-3 text-center">Mientras tanto, las alertas se envían a:</p>
                        <p class="mt-1 text-center font-mono text-xs">
                            {{ $this->destinatariosPorDefecto->pluck('email')->implode(', ') }}
                        </p>
                    @else
                        <p class="mt-3 text-center text-danger">Ningún usuario puede gestionar alertas: hoy no se envía ningún correo.</p>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</div>
