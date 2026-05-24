<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarPreferenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'email'  => ['required', 'email', 'max:255', Rule::unique('users')->ignore(auth()->id())],
            'idioma' => 'required|in:es,en,fr',
        ];
    }
}
