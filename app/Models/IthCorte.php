<?php

namespace App\Models;

use App\Models\Concerns\SeSincroniza;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nodo_id', 'inicio', 'fin', 'promedio', 'maximo', 'minimo', 'cantidad_lecturas'])]
class IthCorte extends Model
{
    /** @use HasFactory<\Database\Factories\IthCorteFactory> */
    use HasFactory, SeSincroniza;

    public const HORAS = 6;

    protected $table = 'ith_cortes';

    protected function casts(): array
    {
        return [
            'inicio' => 'immutable_datetime',
            'fin' => 'immutable_datetime',
            'promedio' => 'decimal:2',
            'maximo' => 'decimal:2',
            'minimo' => 'decimal:2',
            'cantidad_lecturas' => 'integer',
        ];
    }

    /**
     * Inicio del corte (00, 06, 12 o 18) que contiene $momento.
     */
    public static function inicioDelCorte(CarbonInterface $momento): CarbonImmutable
    {
        $local = CarbonImmutable::instance($momento)->setTimezone(config('app.timezone'));

        return $local->setTime(intdiv($local->hour, self::HORAS) * self::HORAS, 0);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }
}
