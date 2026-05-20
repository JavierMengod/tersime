<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramacionInformeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'         => 'required|string|max:255',
            'tipo_periodo'   => 'required|in:horas,dias,meses',
            'valor_periodo'  => 'required|integer|min:1|max:8760',
            'dispositivos'   => 'required|array|min:1',
            'dispositivos.*' => ['integer', Rule::exists('user_dispositivo', 'dispositivo_id')->where('user_id', auth()->id())],
            'hora_inicio'    => 'nullable|date_format:H:i',
            'correo'         => 'sometimes|boolean',
            'correo_destino' => 'nullable|email|required_if:correo,1',
            'telegram'       => 'sometimes|boolean',
            'discord'        => 'sometimes|boolean',
            'activo'         => 'sometimes|boolean',
        ];
    }
}
