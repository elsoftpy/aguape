<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Livewire\Concerns\AnalizaDecimales;
use App\Models\Establecimiento;
use App\Models\Nodo;
use App\Models\Potrero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Zonas / potreros')]
class Zonas extends Component
{
    use AnalizaDecimales, Interactions;

    public ?int $editandoId = null;

    public string $nombre = '';

    public ?string $hectareas = null;

    public bool $mostrarModal = false;

    #[Url(as: 'establecimiento')]
    public string $establecimientoId = '';

    public function mount(): void
    {
        $this->normalizarEstablecimiento();
    }

    public function updatedEstablecimientoId(): void
    {
        $this->normalizarEstablecimiento();
    }

    public function abrirCreacion(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarPotreros->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para registrar potreros.')->send();

            return;
        }

        if ($this->establecimiento === null) {
            $this->toast()->error('Sin establecimiento', 'Registrá un establecimiento antes de cargar potreros.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->mostrarModal = true;
    }

    public function abrirEdicion(Potrero $potrero): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarPotreros->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para editar potreros.')->send();

            return;
        }

        $this->editandoId = $potrero->id;
        $this->nombre = $potrero->nombre;
        $this->hectareas = $this->formatearDecimal($potrero->hectareas);

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarPotreros->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar potreros.')->send();

            return;
        }

        // Al editar, el potrero conserva su establecimiento.
        $establecimientoId = $this->editandoId
            ? Potrero::query()->findOrFail($this->editandoId)->establecimiento_id
            : $this->establecimiento?->id;

        if ($establecimientoId === null) {
            $this->toast()->error('Sin establecimiento', 'Registrá un establecimiento antes de cargar potreros.')->send();

            return;
        }

        $hectareas = $this->analizarDecimal($this->hectareas);

        $datos = Validator::make([
            'nombre' => $this->nombre,
            'hectareas' => $hectareas === false ? $this->hectareas : $hectareas,
        ], [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('potreros', 'nombre')
                    ->where('establecimiento_id', $establecimientoId)
                    ->ignore($this->editandoId),
            ],
            'hectareas' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ])->validate();

        $atributos = [
            'establecimiento_id' => $establecimientoId,
            'nombre' => $datos['nombre'],
            'hectareas' => $datos['hectareas'],
        ];

        if ($this->editandoId) {
            Potrero::query()->findOrFail($this->editandoId)->update($atributos);
        } else {
            Potrero::query()->create($atributos);
        }

        $this->mostrarModal = false;
        $this->resetearFormulario();

        $this->toast()->success('Guardado', 'Potrero guardado correctamente.')->send();
    }

    public function confirmarEliminacion(Potrero $potrero): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarPotreros->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para eliminar potreros.')->send();

            return;
        }

        $this->dialog()
            ->question('¿Eliminar este potrero?', 'Sus nodos no se eliminan: quedan sin zona asignada, con sus lecturas y alertas intactas.')
            ->confirm('Sí, eliminar', 'eliminar', $potrero->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function eliminar(int $id): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarPotreros->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para eliminar potreros.')->send();

            return;
        }

        Potrero::query()->findOrFail($id)->delete();

        $this->toast()->success('Eliminado', 'El potrero fue eliminado.')->send();
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->nombre = '';
        $this->hectareas = null;
        $this->resetValidation();
    }

    private function normalizarEstablecimiento(): void
    {
        if ($this->establecimientoId === '' || ! Establecimiento::query()->whereKey($this->establecimientoId)->exists()) {
            $this->establecimientoId = (string) (Establecimiento::query()->orderBy('nombre')->value('id') ?? '');
        }

        unset($this->establecimiento);
    }

    #[Computed]
    public function potreros(): Collection
    {
        return Potrero::query()
            ->where('establecimiento_id', $this->establecimientoId)
            ->with(['nodos' => fn ($query) => $this->conUltimaLectura($query)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function nodosSinZona(): Collection
    {
        return $this->conUltimaLectura(Nodo::query()->where('establecimiento_id', $this->establecimientoId)->whereNull('potrero_id'))
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return $this->establecimientoId !== ''
            ? Establecimiento::query()->find($this->establecimientoId)
            : null;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function establecimientoOptions(): array
    {
        return Establecimiento::query()->orderBy('nombre')->get()
            ->map(fn (Establecimiento $establecimiento) => ['label' => $establecimiento->nombre, 'value' => $establecimiento->id])
            ->all();
    }

    private function conUltimaLectura(Builder|HasMany $query): Builder|HasMany
    {
        return $query
            ->withMax('lecturas', 'medido_en')
            ->withCasts(['lecturas_max_medido_en' => 'datetime']);
    }

    public function render()
    {
        return view('livewire.pages.zonas');
    }
}
