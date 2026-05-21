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
        $usuario           = $this->route('user');
        $idUsuario         = $usuario ? $usuario->id : null;
        $reglaNombre       = 'required|string|max:255|unique:users,name' . ($idUsuario ? ",{$idUsuario}" : '');
        $reglaContrasena   = $idUsuario ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed';
        $valoresZona       = implode(',', array_keys(self::timezones()));

        return [
            'name'     => $reglaNombre,
            'password' => $reglaContrasena,
            'language' => 'required|in:es,en,fr',
            'timezone' => "required|string|in:{$valoresZona}",
            'theme'    => 'required|in:light,dark',
            'admin'    => 'sometimes|boolean',
        ];
    }
}
