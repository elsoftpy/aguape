<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Historial de mediciones" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <x-filtros.nodo-variable-fecha
            :nodo-options="$this->nodoFiltroOptions"
            :variable-options="$this->variableFiltroOptions"
            :rango-invalido="$this->rangoDeFechasInvalido"
            :hay-filtros="$this->hayFiltros"
        />

        @unless ($this->rangoDeFechasInvalido)
            <div class="overflow-x-auto rounded-[10px] border border-border bg-panel">
                @if ($this->lecturas->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                        {{ $this->hayLecturas ? 'No se encontraron mediciones con los filtros seleccionados.' : 'Todavía no hay mediciones registradas.' }}
                    </p>
                @else
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="border-b border-border text-xs tracking-wide text-text-tertiary uppercase">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Fecha y hora</th>
                                <th class="px-4 py-2.5 font-semibold">Nodo</th>
                                <th class="px-4 py-2.5 font-semibold">Variable</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->lecturas as $lectura)
                                <tr wire:key="lectura-{{ $lectura->id }}" class="border-b border-border last:border-b-0">
                                    <td class="px-4 py-2.5 font-mono text-xs text-text-secondary">{{ $lectura->medido_en->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $lectura->nodo->nombre }}</td>
                                    <td class="px-4 py-2.5 text-text-primary">{{ $lectura->variable->nombre }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-text-primary">
                                        {{ \Illuminate\Support\Number::format((float) $lectura->valor, precision: 2) }}
                                        <span class="text-xs text-text-tertiary">{{ $lectura->variable->unidad }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($this->lecturas->hasPages())
                <div class="flex items-center justify-between gap-3 text-sm">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($this->lecturas->onFirstPage())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Anterior
                    </button>

                    <span class="font-mono text-xs text-text-tertiary">
                        Página {{ $this->lecturas->currentPage() }} de {{ $this->lecturas->lastPage() }}
                    </span>

                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled(! $this->lecturas->hasMorePages())
                        class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Siguiente
                    </button>
                </div>
            @endif
        @endunless
    </div>
</div>