<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['tabla', 'registro_id', 'operacion', 'version', 'intentos', 'ultimo_error'])]
/**
 * Registro local pendiente de enviar a la nube. `version` evita borrar uno que cambió durante el envío.
 */
class SincronizacionPendiente extends Model
{
    public const GUARDAR = 'guardar';

    public const ELIMINAR = 'eliminar';

    /**
     * @var array<string, class-string<Model>>
     */
    public const TABLAS = [
        'users' => User::class,
        'establecimientos' => Establecimiento::class,
        'variables' => Variable::class,
        'configuracion_general' => ConfiguracionGeneral::class,
        'potreros' => Potrero::class,
        'nodos' => Nodo::class,
        'umbrales' => Umbral::class,
        'lecturas' => Lectura::class,
        'alertas' => Alerta::class,
        'ith_cortes' => IthCorte::class,
    ];

    protected $table = 'sincronizacion_pendientes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registro_id' => 'integer',
            'version' => 'integer',
            'intentos' => 'integer',
        ];
    }

    public static function registrar(string $tabla, int $registroId, string $operacion = self::GUARDAR): void
    {
        if (config('app.rol') !== 'local') {
            return;
        }

        DB::table('sincronizacion_pendientes')->upsert(
            [[
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'operacion' => $operacion,
                'version' => 1,
                'intentos' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['tabla', 'registro_id'],
            ['operacion' => $operacion, 'version' => DB::raw('version + 1'), 'updated_at' => now()],
        );
    }

    public function prioridad(): int
    {
        return (int) array_search($this->tabla, array_keys(self::TABLAS), true);
    }
}
