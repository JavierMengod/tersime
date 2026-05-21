<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAjustesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $valoresZona = implode(',', array_keys(UserRequest::timezones()));

        return [
            'theme'     => 'required|in:light,dark',
            'timezone'  => "required|string|in:{$valoresZona}",
            'coste_kwh' => 'required|numeric|min:0|max:99',
        ];
    }
}
