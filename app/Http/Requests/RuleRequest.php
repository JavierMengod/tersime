<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function validationRules(): array
    {
        return [
            'name'              => 'required|string|max:100',
            'devices'           => 'required|array|min:1',
            'devices.*'         => 'integer|exists:dispositivos,id',
            'operator'          => 'required|in:>,<,==,!=,>=,<=',
            'value'             => 'required|numeric',
            'for_duration'      => 'required|integer|min:0',
            'methods'           => 'nullable|array',
            'methods.*'         => 'in:telegram,email,discord',
            'template_telegram' => 'nullable|string',
            'template_email'    => 'nullable|string',
            'template_discord'  => 'nullable|string',
            'recipient_email'   => 'nullable|email',
        ];
    }

    public function rules(): array
    {
        return static::validationRules();
    }
}
