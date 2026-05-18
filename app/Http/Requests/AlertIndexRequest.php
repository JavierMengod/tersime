<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device'   => 'nullable|string',
            'rule'     => 'nullable|string',
            'type'     => 'nullable|in:firing,resolution',
            'from'     => 'nullable|date_format:Y-m-d',
            'to'       => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
