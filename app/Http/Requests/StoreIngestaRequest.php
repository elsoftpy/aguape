<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreIngestaRequest extends FormRequest
{
    /**
     * La autorización la resuelve el middleware `ingesta.token` (RF04), no
     * un usuario autenticado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'exists:nodos,codigo'],
            'medido_en' => ['required', 'date'],
            // Con batería crítica el nodo no mide: el mensaje trae solo la batería.
            'nivel_bateria' => ['nullable', 'numeric', 'required_without:lecturas'],
            'lecturas' => ['present', 'array', 'required_without:nivel_bateria'],
            'lecturas.*.variable' => ['required', 'string', 'exists:variables,clave', 'not_in:nivel_bateria,ith'],
            'lecturas.*.valor' => ['required', 'numeric'],
        ];
    }

    /**
     * Registra el intento rechazado en el log dedicado de ingesta (RF04)
     * antes de lanzar la excepción de validación estándar.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::channel('ingesta')->warning('rechazado_validacion', [
            'codigo' => $this->input('codigo'),
            'errores' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}