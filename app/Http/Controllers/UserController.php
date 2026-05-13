<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('usuarios.index', compact('users'));
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user'     => 'required|string|max:255',
            'password' => 'required|string',
            'language' => 'nullable|string|in:es,en,fr', // Agregar más idiomas si es necesario
            'timezone' => 'nullable|string',
            'theme'    => 'nullable|string|in:light,dark',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name'       => $request->user,
            'password'   => Hash::make($request->password),
            'language'   => $request->language ?? 'es',
            'timezone'   => $request->timezone ?? 'UTC+01:00',
            'theme'      => $request->theme ?? 'light',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Usuario creado exitosamente');
    }
}
