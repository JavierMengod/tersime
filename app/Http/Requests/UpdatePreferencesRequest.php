<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tzValues = implode(',', array_keys(UserRequest::timezones()));

        return [
            'language' => 'required|in:es,en,fr',
            'theme'    => 'required|in:light,dark',
            'timezone' => "required|string|in:{$tzValues}",
        ];
    }
}
