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

    public string $establecimientoId = '';

    /**
     * String, no `?int` — `<x-select.native>` siempre envía un string, y un
     * valor vacío ('') representa "sin zona asignada" (ver `potreroOptions()`).
     */
    public string $potreroId = '';

    public int $intervaloTransmisionMinutos = 15;

    /**
     * Fecha `YYYY-MM-DD` que envía `<x-date>`. Al crear arranca en hoy.
     */
    public ?string $instaladoEn = null;

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
        $this->establecimientoId = (string) $nodo->establecimiento_id;
        $this->potreroId = $nodo->potrero_id !== null ? (string) $nodo->potrero_id : '';
        $this->intervaloTransmisionMinutos = $nodo->intervalo_transmision_minutos;
        $this->instaladoEn = $nodo->instalado_en?->toDateString();
        $this->activo = $nodo->activo;

        $this->mostrarModal = true;
    }

    public function updatedEstablecimientoId(): void
    {
        if ($this->potreroId !== '' && ! $this->potreroPerteneceAlEstablecimiento()) {
            $this->potreroId = '';
        }
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarNodos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar nodos.')->send();

            return;
        }

        $potreroId = $this->potreroId === '' ? null : (int) $this->potreroId;

        $datos = $this->validate([
            'establecimientoId' => ['required', Rule::exists('establecimientos', 'id')],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('nodos', 'codigo')->ignore($this->editandoId)],
            'nombre' => ['required', 'string', 'max:100'],
            'intervaloTransmisionMinutos' => ['required', 'integer', 'between:15,120'],
            'instaladoEn' => ['required', 'date', 'before_or_equal:today'],
            'activo' => ['boolean'],
        ], [
            'instaladoEn.before_or_equal' => 'La fecha de instalación no puede ser posterior a hoy.',
        ]);

        if ($potreroId !== null && ! $this->potreroPerteneceAlEstablecimiento()) {
            $this->addError('potreroId', 'La zona seleccionada no pertenece al establecimiento.');

            return;
        }

        $atributos = [
            'establecimiento_id' => (int) $datos['establecimientoId'],
            'potrero_id' => $potreroId,
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'intervalo_transmision_minutos' => $datos['intervaloTransmisionMinutos'],
            'instalado_en' => $datos['instaladoEn'],
            'activo' => $datos['activo'],
        ];

        if ($this->editandoId) {
            Nodo::query()->findOrFail($this->editandoId)->update($atributos);
        } else {
            Nodo::query()->create($atributos);
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

        if ($this->tieneLecturas($nodo)) {
            return;
        }

        $this->dialog()
            ->question('¿Eliminar este nodo?', 'Esta acción no se puede deshacer.')
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

        $nodo = Nodo::query()->findOrFail($id);

        if ($this->tieneLecturas($nodo)) {
            return;
        }

        $nodo->delete();

        $this->toast()->success('Eliminado', 'El nodo fue eliminado.')->send();
    }

    private function tieneLecturas(Nodo $nodo): bool
    {
        if (! $nodo->lecturas()->exists()) {
            return false;
        }

        $this->toast()->error('No se puede eliminar', 'El nodo tiene mediciones registradas. Para retirarlo, desactivalo.')->send();

        return true;
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->codigo = '';
        $this->nombre = '';
        $this->establecimientoId = (string) (Establecimiento::unicoRegistrado()?->id ?? '');
        $this->potreroId = '';
        $this->intervaloTransmisionMinutos = 15;
        $this->instaladoEn = today()->toDateString();
        $this->activo = true;
        $this->resetValidation();
    }

    private function potreroPerteneceAlEstablecimiento(): bool
    {
        return Potrero::query()
            ->whereKey($this->potreroId)
            ->where('establecimiento_id', $this->establecimientoId)
            ->exists();
    }

    /**
     * @return LengthAwarePaginator<int, Nodo>
     */
    #[Computed]
    public function nodos(): LengthAwarePaginator
    {
        return Nodo::query()
            ->with(['establecimiento', 'potrero'])
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
            ...Potrero::query()->where('establecimiento_id', $this->establecimientoId)->orderBy('nombre')->get()
                ->map(fn (Potrero $potrero) => ['label' => $potrero->nombre, 'value' => $potrero->id])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    #[Computed]
    public function establecimientoOptions(): array
    {
        return [
            ['label' => 'Elegí un establecimiento', 'value' => ''],
            ...Establecimiento::query()->orderBy('nombre')->get()
                ->map(fn (Establecimiento $establecimiento) => ['label' => $establecimiento->nombre, 'value' => $establecimiento->id])
                ->all(),
        ];
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.nodos');
    }
}
