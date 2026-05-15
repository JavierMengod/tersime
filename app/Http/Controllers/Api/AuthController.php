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
            'name'     => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['name' => $request->name, 'password' => $request->password])) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $user = Auth::user();

        if (!$user->enabled) {
            Auth::logout();
            return response()->json(['message' => 'Esta cuenta está deshabilitada.'], 403);
        }

        $tokenName = $request->input('device_name', 'api-token');
        $token     = $user->createToken($tokenName)->plainTextToken;

        Log::info('[API] Login: ' . $user->name);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'language' => $user->language,
                'timezone' => $user->timezone,
                'theme'    => $user->theme,
                'admin'    => $user->admin,
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
            'id'         => $user->id,
            'name'       => $user->name,
            'language'   => $user->language,
            'timezone'   => $user->timezone,
            'theme'      => $user->theme,
            'admin'      => $user->admin,
            'enabled'    => $user->enabled,
            'created_at' => $user->created_at,
        ]);
    }
}
