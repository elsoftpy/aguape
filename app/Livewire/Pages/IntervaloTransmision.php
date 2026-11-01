<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Models\ConfiguracionGeneral;
use App\Models\Establecimiento;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Intervalo de transmisión')]
class IntervaloTransmision extends Component
{
    use Interactions;

    /**
     * String y no int: <x-number> falla al renderizar un valor fuera de min/max.
     */
    public string $intervaloTransmisionMinutos = '';

    public function mount(): void
    {
        $this->intervaloTransmisionMinutos = (string) $this->configuracion->intervalo_transmision_minutos;
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarConfiguracion->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para modificar la configuración.')->send();

            return;
        }

        $datos = $this->validate([
            'intervaloTransmisionMinutos' => ['required', 'integer', 'between:'.ConfiguracionGeneral::INTERVALO_MINIMO.','.ConfiguracionGeneral::INTERVALO_MAXIMO],
        ], [
            'intervaloTransmisionMinutos.required' => 'Ingresá un número entero de minutos.',
            'intervaloTransmisionMinutos.integer' => 'Ingresá un número entero de minutos.',
            'intervaloTransmisionMinutos.between' => 'El intervalo debe estar entre :min y :max minutos.',
        ]);

        $this->configuracion->cambiarIntervalo((int) $datos['intervaloTransmisionMinutos']);
        unset($this->configuracion);

        $this->toast()->success('Guardado', 'Los nodos aplican el nuevo intervalo en su próxima transmisión.')->send();
    }

    public function cancelar(): void
    {
        $this->resetValidation();
        $this->intervaloTransmisionMinutos = (string) $this->configuracion->intervalo_transmision_minutos;
    }

    #[Computed]
    public function configuracion(): ConfiguracionGeneral
    {
        return ConfiguracionGeneral::actual();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.intervalo-transmision');
    }
}
