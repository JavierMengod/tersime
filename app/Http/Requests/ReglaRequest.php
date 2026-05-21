<?php

namespace App\Http\Requests;

use App\Models\Regla as ReglaModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReglaRequest extends FormRequest
{
    private ?ReglaModel $reglaResuelta = null;

    public function authorize(): bool
    {
        $id = $this->route('id');

        if ($id !== null) {
            $usuario             = $this->user();
            $this->reglaResuelta = ReglaModel::find($id);

            if (!$this->reglaResuelta || !$usuario || (int) $this->reglaResuelta->user_id !== (int) $usuario->id) {
                abort(404);
            }
        }

        return true;
    }

    public function reglaResuelta(): ?ReglaModel
    {
        return $this->reglaResuelta;
    }

    public function rules(): array
    {
        $idUsuario = $this->user() ? $this->user()->id : null;

        return [
            'name'              => 'required|string|max:100',
            'devices'           => 'required|array|min:1',
            'devices.*'         => ['integer', Rule::exists('user_dispositivo', 'dispositivo_id')
                                        ->where('user_id', $idUsuario)],
            'operator'          => 'required|in:>,<,==,!=,>=,<=',
            'value'             => 'required|numeric',
            'for_duration'      => 'required|integer|min:0|max:168',
            'methods'           => 'nullable|array',
            'methods.*'         => 'in:telegram,email,discord',
            'template_telegram' => 'nullable|string|max:1000',
            'template_email'    => 'nullable|string|max:1000',
            'template_discord'  => 'nullable|string|max:1000',
            'recipient_email'   => 'nullable|email',
        ];
    }
}
