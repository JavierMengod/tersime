<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['nombre' => $request->nombre, 'password' => $request->password])) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $user = Auth::user();

        if (!$user->activo) {
            Auth::logout();
            return response()->json(['message' => 'Esta cuenta está deshabilitada.'], 403);
        }

        $tokenName = $request->input('device_name', 'api-token');
        $token     = $user->createToken($tokenName)->plainTextToken;

        Log::info('[API] Login: ' . $user->nombre);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'           => $user->id,
                'nombre'       => $user->nombre,
                'idioma'       => $user->idioma,
                'zona_horaria' => $user->zona_horaria,
                'tema'         => $user->tema,
                'administrador'=> $user->administrador,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'            => $user->id,
            'nombre'        => $user->nombre,
            'idioma'        => $user->idioma,
            'zona_horaria'  => $user->zona_horaria,
            'tema'          => $user->tema,
            'administrador' => $user->administrador,
            'activo'        => $user->activo,
            'created_at'    => $user->created_at,
        ]);
    }
}
