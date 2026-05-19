<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Muestra el formulario de login
    public function showLoginForm()
    {
        return view('login');
    }

    // Procesa el login

    public function login(Request $request)
    {
        // Registrar valores de entrada en el archivo de log
        Log::info('Intento de login', ['name' => $request->user]);

        // Validación de los datos
        $request->validate([
            'user' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::attempt(['name' => $request->user, 'password' => $request->password])) {
            if (!Auth::user()->enabled) {
                Auth::logout();
                Log::warning('Login denegado (usuario deshabilitado): ' . $request->user);
                return back()->withErrors(['username' => 'Esta cuenta está deshabilitada.']);
            }
            $request->session()->regenerate();
            Log::info('Login exitoso para el usuario: ' . $request->user);
            return redirect()->intended('/inicio');
        }

        Log::warning('Login fallido para el usuario: ' . $request->user);
        return back()->withErrors([
            'username' => 'Las credenciales no son correctas.',
        ]);
    }

    // Cerrar sesión
    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }

    public function updateLanguage(Request $request)
    {
        
        $request->validate([
            'language' => 'required|in:es,en,fr', // valida que sea uno de los idiomas permitidos
        ]);

        $user = auth()->user();
        $user->language = $request->language;
        $user->save();
        Log::info('Se ha solicitado cambiar un lenguaje para un usuario');
        
        return redirect()->back()->with('status', 'Idioma actualizado con éxito.');
    }
}
