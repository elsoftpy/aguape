<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Alertas" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="setTab('pendiente')"
                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition
                    {{ $tab === 'pendiente' ? 'bg-accent/15 text-accent' : 'text-text-secondary hover:bg-surface hover:text-text-primary' }}"
            >
                Activas
                <span class="ml-1 font-mono text-xs text-text-tertiary">{{ $this->totalPendientes }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('atendida')"
                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition
                    {{ $tab === 'atendida' ? 'bg-accent/15 text-accent' : 'text-text-secondary hover:bg-surface hover:text-text-primary' }}"
            >
                Revisadas
                <span class="ml-1 font-mono text-xs text-text-tertiary">{{ $this->totalRevisadas }}</span>
            </button>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->alertas as $alerta)
                <div wire:key="alerta-{{ $alerta->id }}" class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-icon name="exclamation-triangle" class="size-4 shrink-0 text-warning" />

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text-primary">
                                {{ $alerta->variable->nombre }} · {{ $alerta->nodo->nombre }}
                                @if ($alerta->nodo->potrero)
                                    · {{ $alerta->nodo->potrero->nombre }}
                                @endif
                            </p>
                            <p class="truncate font-mono text-[11px] text-text-tertiary">
                                {{ \Illuminate\Support\Number::format((float) $alerta->lectura->valor, precision: 2)}}{{ $alerta->variable->unidad }}
                                ({{ $alerta->umbral->tipo->value }} {{ \Illuminate\Support\Number::format((float) $alerta->umbral->valor, precision: 2)}})
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        @if ($tab === 'atendida')
                            <span class="text-right font-mono text-[11px] text-text-tertiary">
                                Revisada por {{ $alerta->atendidaPor?->name ?? '—' }}
                                <br>
                                {{ $alerta->atendida_en?->diffForHumans() ?? $alerta->created_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="font-mono text-[11px] text-text-tertiary">{{ $alerta->created_at->diffForHumans() }}</span>
                        @endif

                        @if ($tab === 'pendiente' && auth()->user()->can(\App\Enums\PermissionsEnum::GestionarAlertas->value))
                            <button
                                type="button"
                                wire:click="confirmarRevision({{ $alerta->id }})"
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                            >
                                <x-icon name="check" class="size-3.5" />
                                Marcar como revisada
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                    {{ $tab === 'pendiente' ? 'No hay alertas activas.' : 'No hay alertas revisadas.' }}
                </p>
            @endforelse
        </div>

        @if ($this->alertas->hasPages())
            <div class="flex items-center justify-between gap-3 text-sm">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($this->alertas->onFirstPage())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Anterior
                </button>

                <span class="font-mono text-xs text-text-tertiary">
                    Página {{ $this->alertas->currentPage() }} de {{ $this->alertas->lastPage() }}
                </span>

                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled(! $this->alertas->hasMorePages())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Siguiente
                </button>
            </div>
        @endif
    </div>
</div>