<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['intervalo_transmision_minutos', 'version'])]
class ConfiguracionGeneral extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ConfiguracionGeneralFactory> */
    use Auditable, HasFactory;

    public const INTERVALO_MINIMO = 15;

    public const INTERVALO_MAXIMO = 120;

    protected $table = 'configuracion_general';

    protected function casts(): array
    {
        return [
            'intervalo_transmision_minutos' => 'integer',
            'version' => 'integer',
        ];
    }

    public static function actual(): self
    {
        return self::query()->oldest('id')->firstOr(
            fn () => self::query()->create(['intervalo_transmision_minutos' => self::INTERVALO_MINIMO, 'version' => 1]),
        );
    }

    /**
     * Solo sube la versión si el intervalo cambió.
     */
    public function cambiarIntervalo(int $minutos): void
    {
        if ($minutos === $this->intervalo_transmision_minutos) {
            return;
        }

        $this->update([
            'intervalo_transmision_minutos' => $minutos,
            'version' => $this->version + 1,
        ]);
    }

    /**
     * cfg_version de las tramas: de 1 a 255 (0 = nodo sin configuración).
     */
    public function cfgVersionNodos(): int
    {
        return (($this->version - 1) % 255) + 1;
    }
}
