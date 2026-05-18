<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public static function timezones(): array
    {
        return [
            'Europe/Madrid'       => 'Europe/Madrid (ES)',
            'Europe/London'       => 'Europe/London (UK)',
            'Europe/Paris'        => 'Europe/Paris (FR)',
            'Europe/Berlin'       => 'Europe/Berlin (DE)',
            'America/New_York'    => 'America/New_York (US East)',
            'America/Chicago'     => 'America/Chicago (US Central)',
            'America/Denver'      => 'America/Denver (US Mountain)',
            'America/Los_Angeles' => 'America/Los_Angeles (US West)',
            'America/Sao_Paulo'   => 'America/Sao_Paulo (BR)',
            'Asia/Tokyo'          => 'Asia/Tokyo (JP)',
            'Asia/Shanghai'       => 'Asia/Shanghai (CN)',
            'UTC'                 => 'UTC',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user       = $this->route('user');
        $userId     = $user ? $user->id : null;
        $nameRule   = 'required|string|max:255|unique:users,name' . ($userId ? ",{$userId}" : '');
        $passRule   = $userId ? 'nullable|string|min:6|confirmed' : 'required|string|min:6|confirmed';
        $tzValues   = implode(',', array_keys(self::timezones()));

        return [
            'name'     => $nameRule,
            'password' => $passRule,
            'language' => 'required|in:es,en,fr',
            'timezone' => "required|string|in:{$tzValues}",
            'theme'    => 'required|in:light,dark',
            'admin'    => 'sometimes|boolean',
        ];
    }
}
