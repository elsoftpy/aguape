<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Models\Establecimiento;
use App\Models\Nodo;
use App\Models\Potrero;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Nodos LoRa')]
class Nodos extends Component
{
    use Interactions, WithPagination;

    /**
     * `null` en modo creación, id del nodo en edición en modo edición.
     */
    public ?int $editandoId = null;

    public string $codigo = '';

    public string $nombre = '';

    /**
     * String, no `?int` — `<x-select.native>` siempre envía un string, y un
     * valor vacío ('') representa "sin zona asignada" (ver `potreroOptions()`).
     */
    public string $potreroId = '';

    public int $intervaloTransmisionMinutos = 15;

    public bool $activo = true;

    public bool $mostrarModal = false;

    public function abrirCreacion(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para registrar nodos.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->mostrarModal = true;
    }

    public function abrirEdicion(Nodo $nodo): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para editar nodos.')->send();

            return;
        }

        $this->editandoId = $nodo->id;
        $this->codigo = $nodo->codigo;
        $this->nombre = $nodo->nombre;
        $this->potreroId = $nodo->potrero_id !== null ? (string) $nodo->potrero_id : '';
        $this->intervaloTransmisionMinutos = $nodo->intervalo_transmision_minutos;
        $this->activo = $nodo->activo;

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar nodos.')->send();

            return;
        }

        $potreroId = $this->potreroId === '' ? null : (int) $this->potreroId;

        if ($potreroId !== null && ! Potrero::query()->whereKey($potreroId)->exists()) {
            $this->addError('potreroId', 'La zona seleccionada no es válida.');

            return;
        }

        $datos = $this->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('nodos', 'codigo')->ignore($this->editandoId)],
            'nombre' => ['required', 'string', 'max:100'],
            'intervaloTransmisionMinutos' => ['required', 'integer', 'between:15,120'],
            'activo' => ['boolean'],
        ]);

        $atributos = [
            'establecimiento_id' => $this->establecimiento()?->id,
            'potrero_id' => $potreroId,
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'intervalo_transmision_minutos' => $datos['intervaloTransmisionMinutos'],
            'activo' => $datos['activo'],
        ];

        if ($this->editandoId) {
            Nodo::query()->findOrFail($this->editandoId)->update($atributos);
        } else {
            Nodo::query()->create($atributos + ['instalado_en' => now()]);
        }

        $this->mostrarModal = false;
        $this->resetearFormulario();

        $this->toast()->success('Guardado', 'Nodo guardado correctamente.')->send();
    }

    public function confirmarEliminacion(Nodo $nodo): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para eliminar nodos.')->send();

            return;
        }

        $this->dialog()
            ->question('¿Eliminar este nodo?', 'También se eliminarán sus lecturas, umbrales y alertas asociadas. Esta acción no se puede deshacer.')
            ->confirm('Sí, eliminar', 'eliminar', $nodo->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function eliminar(int $id): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para eliminar nodos.')->send();

            return;
        }

        Nodo::query()->findOrFail($id)->delete();

        $this->toast()->success('Eliminado', 'El nodo fue eliminado.')->send();
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->codigo = '';
        $this->nombre = '';
        $this->potreroId = '';
        $this->intervaloTransmisionMinutos = 15;
        $this->activo = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, Nodo>
     */
    #[Computed]
    public function nodos(): LengthAwarePaginator
    {
        return Nodo::query()
            ->with(['potrero'])
            ->orderBy('codigo')
            ->paginate(10);
    }

    /**
     * Opciones para el select de zona/potrero, con un valor vacío inicial
     * para representar "sin zona asignada" (`potrero_id` nulo en `Nodo`).
     *
     * @return array<int, array{label: string, value: int|string}>
     */
    #[Computed]
    public function potreroOptions(): array
    {
        return [
            ['label' => 'Sin zona asignada', 'value' => ''],
            ...Potrero::query()->orderBy('nombre')->get()
                ->map(fn (Potrero $potrero) => ['label' => $potrero->nombre, 'value' => $potrero->id])
                ->all(),
        ];
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.nodos');
    }
}
