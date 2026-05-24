<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SolicitudResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PasswordResetRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
        ]);

        $nombreUsuario = $request->input('username');
        $ip            = $request->ip();

        $usuario = User::where('nombre', $nombreUsuario)->first();

        if ($usuario && $usuario->activo) {
            $administradores = User::where('administrador', true)->where('activo', true)->get();

            foreach ($administradores as $administrador) {
                $administrador->notify(new SolicitudResetPassword($nombreUsuario, $ip));
            }

            Log::info("[PasswordReset] Solicitud enviada a admins para usuario: {$nombreUsuario}", ['ip' => $ip]);
        } else {
            Log::info("[PasswordReset] Solicitud para usuario inexistente o deshabilitado: {$nombreUsuario}", ['ip' => $ip]);
        }

        return back()->with('reset_sent', true);
    }
}
