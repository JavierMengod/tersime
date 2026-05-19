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

        $username = $request->input('username');
        $ip       = $request->ip();

        // Look up the user — always return the same response to prevent enumeration
        $user = User::where('name', $username)->first();

        if ($user && $user->enabled) {
            $admins = User::where('admin', true)->where('enabled', true)->get();

            foreach ($admins as $admin) {
                $admin->notify(new SolicitudResetPassword($username, $ip));
            }

            Log::info("[PasswordReset] Solicitud enviada a admins para usuario: {$username}", ['ip' => $ip]);
        } else {
            // Log internally but don't reveal to client whether user exists
            Log::info("[PasswordReset] Solicitud para usuario inexistente o deshabilitado: {$username}", ['ip' => $ip]);
        }

        // Always show the same success message
        return back()->with('reset_sent', true);
    }
}
