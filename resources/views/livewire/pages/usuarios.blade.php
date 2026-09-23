<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Usuarios y roles">
        <x-slot:actions>
            <x-button text="Nuevo usuario" icon="plus" wire:click="abrirCreacion" />
        </x-slot:actions>
    </x-layouts.page-header>

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Administrá los usuarios del sistema y su rol (Administrador, Operador o Visor). Al crear un usuario se le envía por correo un enlace para crear su contraseña (vence en 48 horas). Un usuario inactivo no puede iniciar sesión, y al desactivarlo se cierran sus sesiones abiertas.
        </p>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->usuarios as $usuario)
                <div wire:key="usuario-{{ $usuario->id }}" class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon name="user" sm />
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text-primary">
                                {{ $usuario->name }}
                                @if ($usuario->is(auth()->user()))
                                    <span class="text-xs text-text-tertiary">(vos)</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-text-tertiary">{{ $usuario->email }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <span class="text-xs text-text-secondary">
                            {{ \Illuminate\Support\Str::ucfirst($usuario->roles->first()?->name ?? 'Sin rol') }}
                        </span>

                        <span class="rounded-full px-2.5 py-1 text-xs font-medium
                            {{ $usuario->status === \App\Enums\UserStatus::Activo ? 'bg-success-dim text-success' : 'bg-danger-dim text-danger' }}">
                            {{ $usuario->status === \App\Enums\UserStatus::Activo ? 'Activo' : 'Inactivo' }}
                        </span>

                        <div class="flex items-center gap-2">
                            @unless ($usuario->is(auth()->user()))
                                <button
                                    type="button"
                                    wire:click="reenviarInvitacion({{ $usuario->id }})"
                                    wire:loading.attr="disabled"
                                    class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:opacity-40"
                                >
                                    <x-icon name="envelope" class="size-3.5" />
                                    Reenviar enlace
                                </button>
                            @endunless

                            <button
                                type="button"
                                wire:click="abrirEdicion({{ $usuario->id }})"
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                            >
                                <x-icon name="pencil" class="size-3.5" />
                                Editar
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                    No hay usuarios registrados todavía.
                </p>
            @endforelse
        </div>

        @if ($this->usuarios->hasPages())
            <div class="flex items-center justify-between gap-3 text-sm">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($this->usuarios->onFirstPage())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Anterior
                </button>

                <span class="font-mono text-xs text-text-tertiary">
                    Página {{ $this->usuarios->currentPage() }} de {{ $this->usuarios->lastPage() }}
                </span>

                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled(! $this->usuarios->hasMorePages())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Siguiente
                </button>
            </div>
        @endif
    </div>

    <x-modal id="modal-usuario" wire="mostrarModal" :title="$editandoId ? 'Editar usuario' : 'Nuevo usuario'" center="md">
        <div class="space-y-4">
            <x-input wire:model="name" label="Usuario" hint="Se usa para iniciar sesión" autocomplete="off" />

            <x-input
                wire:model="email"
                label="Correo electrónico"
                type="email"
                autocomplete="off"
                :hint="$editandoId ? null : 'Se le enviará un enlace para crear su contraseña'"
            />

            <x-select.native
                wire:model="rol"
                label="Rol"
                :options="$this->rolOptions"
            />

            <x-toggle wire:model="activo" label="Usuario activo" color="cyan" position="left" />
        </div>

        <x-slot:footer>
            <x-button text="Cancelar" color="red" wire:click="$set('mostrarModal', false)" />
            <x-button text="Guardar" wire:click="guardar" />
        </x-slot:footer>
    </x-modal>
</div>