<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Auditoría" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <div class="rounded-[10px] border border-border bg-panel p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-select.native wire:model.live="usuarioId" label="Usuario" :options="$this->usuarioOptions" />
                <x-select.native wire:model.live="evento" label="Evento" :options="$this->eventoOptions" />
                <x-select.native wire:model.live="entidad" label="Entidad" :options="$this->entidadOptions" />
                <x-date wire:model.live="desde" label="Desde" format="DD/MM/YYYY" />
                <x-date wire:model.live="hasta" label="Hasta" format="DD/MM/YYYY" />
            </div>

            @if ($this->rangoDeFechasInvalido || $this->hayFiltros)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-danger">
                        @if ($this->rangoDeFechasInvalido)
                            La fecha "Hasta" es anterior a "Desde". Corregí el rango para buscar.
                        @endif
                    </p>

                    @if ($this->hayFiltros)
                        <button
                            type="button"
                            wire:click="limpiarFiltros"
                            class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                        >
                            <x-icon name="x-mark" class="size-3.5" />
                            Limpiar filtros
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @unless ($this->rangoDeFechasInvalido)
            <div class="overflow-x-auto rounded-[10px] border border-border bg-panel">
                @if ($this->registros->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                        {{ $this->hayRegistros ? 'No se encontraron registros con los filtros seleccionados.' : 'Todavía no hay registros de auditoría.' }}
                    </p>
                @else
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-border text-xs tracking-wide text-text-tertiary uppercase">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Fecha y hora</th>
                                <th class="px-4 py-2.5 font-semibold">Usuario</th>
                                <th class="px-4 py-2.5 font-semibold">Evento</th>
                                <th class="px-4 py-2.5 font-semibold">Entidad</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->registros as $registro)
                                <tr wire:key="auditoria-{{ $registro->id }}" class="border-b border-border last:border-b-0">
                                    <td class="px-4 py-2.5 font-mono text-xs text-text-secondary">{{ $registro->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $registro->user?->name ?? 'Sistema' }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $this->nombreEvento($registro) }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $this->nombreEntidad($registro) }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <button
                                            type="button"
                                            wire:click="verDetalle({{ $registro->id }})"
                                            class="rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                                        >
                                            Ver detalle
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($this->registros->hasPages())
                <div class="flex items-center justify-between gap-3 text-sm">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($this->registros->onFirstPage())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Anterior
                    </button>

                    <span class="font-mono text-xs text-text-tertiary">
                        Página {{ $this->registros->currentPage() }} de {{ $this->registros->lastPage() }}
                    </span>

                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled(! $this->registros->hasMorePages())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Siguiente
                    </button>
                </div>
            @endif
        @endunless
    </div>

    <x-modal id="modal-auditoria" wire="mostrarDetalle" title="Detalle del registro" center="md" size="3xl" scrollable>
        @if ($detalle = $this->detalle)
            <div class="space-y-4 text-sm">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-[140px_1fr]">
                    <dt class="text-text-tertiary">Fecha y hora</dt>
                    <dd class="font-mono text-text-primary">{{ $detalle->created_at->format('d/m/Y H:i:s') }}</dd>

                    <dt class="text-text-tertiary">Usuario</dt>
                    <dd class="text-text-primary">{{ $detalle->user?->name ?? 'Sistema' }}</dd>

                    <dt class="text-text-tertiary">Evento</dt>
                    <dd class="text-text-primary">{{ $this->nombreEvento($detalle) }}</dd>

                    <dt class="text-text-tertiary">Entidad</dt>
                    <dd class="text-text-primary">{{ $this->nombreEntidad($detalle) }}</dd>

                    <dt class="text-text-tertiary">Dirección IP</dt>
                    <dd class="font-mono text-text-primary">{{ $detalle->ip_address ?? '—' }}</dd>

                    <dt class="text-text-tertiary">URL</dt>
                    <dd class="font-mono text-xs break-all text-text-primary">{{ $detalle->url ?? '—' }}</dd>

                    <dt class="text-text-tertiary">Navegador</dt>
                    <dd class="text-xs break-all text-text-secondary">{{ $detalle->user_agent ?? '—' }}</dd>
                </dl>

                <div class="overflow-x-auto rounded-lg border border-border">
                    @if ($this->cambios === [])
                        <p class="px-3 py-4 text-center text-text-tertiary">El registro no tiene valores modificados.</p>
                    @else
                        <table class="w-full text-left">
                            <thead class="border-b border-border text-xs tracking-wide text-text-tertiary uppercase">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Campo</th>
                                    <th class="px-3 py-2 font-semibold">Valor anterior</th>
                                    <th class="px-3 py-2 font-semibold">Valor nuevo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->cambios as $campo => $valores)
                                    <tr class="border-b border-border last:border-b-0">
                                        <td class="px-3 py-2 font-mono text-xs text-text-secondary">{{ $campo }}</td>
                                        <td class="px-3 py-2 break-all text-text-primary">{{ $valores['anterior'] }}</td>
                                        <td class="px-3 py-2 break-all text-text-primary">{{ $valores['nuevo'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        <x-slot:footer>
            <x-button text="Cerrar" wire:click="$set('mostrarDetalle', false)" />
        </x-slot:footer>
    </x-modal>
</div>
