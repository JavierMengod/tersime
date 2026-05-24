<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\UsuarioRequest;

class ActualizarAjustesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $valoresZona = implode(',', array_keys(UsuarioRequest::timezones()));

        return [
            'tema'        => 'required|in:light,dark',
            'zona_horaria'=> "required|string|in:{$valoresZona}",
            'coste_kwh'   => 'required|numeric|min:0|max:99',
        ];
    }
}
