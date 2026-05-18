<?php

namespace App\Http\Requests;

use App\Models\Rule as RuleModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RuleRequest extends FormRequest
{
    private ?RuleModel $resolvedRule = null;

    public function authorize(): bool
    {
        $id = $this->route('id');

        if ($id !== null) {
            $user               = $this->user();
            $this->resolvedRule = RuleModel::find($id);

            if (!$this->resolvedRule || !$user || (int) $this->resolvedRule->user_id !== (int) $user->id) {
                abort(404);
            }
        }

        return true;
    }

    public function resolvedRule(): ?RuleModel
    {
        return $this->resolvedRule;
    }

    public function rules(): array
    {
        $userId = $this->user() ? $this->user()->id : null;

        return [
            'name'              => 'required|string|max:100',
            'devices'           => 'required|array|min:1',
            'devices.*'         => ['integer', Rule::exists('user_dispositivo', 'dispositivo_id')
                                        ->where('user_id', $userId)],
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
