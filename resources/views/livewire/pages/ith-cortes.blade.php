<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="ITH por cortes" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Índice de Temperatura y Humedad de cada nodo resumido cada seis horas (00–06, 06–12, 12–18 y 18–24, hora local). Un corte se registra al terminar.
        </p>

        <div class="rounded-[10px] border border-border bg-panel p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-select.native wire:model.live="nodoId" label="Nodo" :options="$this->nodoOptions" />
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
                @if ($this->cortes->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                        {{ $this->hayCortes ? 'No se encontraron cortes con los filtros seleccionados.' : 'Todavía no hay cortes de ITH registrados.' }}
                    </p>
                @else
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-border text-xs tracking-wide text-text-tertiary uppercase">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Corte</th>
                                <th class="px-4 py-2.5 font-semibold">Nodo</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Promedio</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Máximo</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Mínimo</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Lecturas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->cortes as $corte)
                                <tr wire:key="corte-{{ $corte->id }}" class="border-b border-border last:border-b-0">
                                    <td class="px-4 py-2.5 font-mono text-xs text-text-secondary">{{ $this->etiquetaCorte($corte) }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $corte->nodo->nombre }}</td>
                                    @foreach (['promedio', 'maximo', 'minimo'] as $campo)
                                        <td class="px-4 py-2.5 text-right font-mono text-text-primary">
                                            {{ \Illuminate\Support\Number::format((float) $corte->{$campo}, precision: 2) }}
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2.5 text-right font-mono text-text-tertiary">{{ $corte->cantidad_lecturas }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($this->cortes->hasPages())
                <div class="flex items-center justify-between gap-3 text-sm">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($this->cortes->onFirstPage())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Anterior
                    </button>

                    <span class="font-mono text-xs text-text-tertiary">
                        Página {{ $this->cortes->currentPage() }} de {{ $this->cortes->lastPage() }}
                    </span>

                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled(! $this->cortes->hasMorePages())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Siguiente
                    </button>
                </div>
            @endif
        @endunless
    </div>
</div>
