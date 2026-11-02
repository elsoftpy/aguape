<?php

namespace App\Http\Requests;

use App\Models\SincronizacionPendiente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreSincronizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registros' => ['required', 'array', 'max:'.max(1000, (int) config('services.sincronizacion.lote'))],
            'registros.*.tabla' => ['required', 'string', Rule::in(array_keys(SincronizacionPendiente::TABLAS))],
            'registros.*.id' => ['required', 'integer', 'min:1'],
            'registros.*.operacion' => ['required', Rule::in([SincronizacionPendiente::GUARDAR, SincronizacionPendiente::ELIMINAR])],
            'registros.*.datos' => ['required_if:registros.*.operacion,'.SincronizacionPendiente::GUARDAR, 'array'],
            'registros.*.rol' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::channel('sincronizacion')->warning('rechazado_validacion', ['errores' => $validator->errors()->toArray()]);

        parent::failedValidation($validator);
    }
}
