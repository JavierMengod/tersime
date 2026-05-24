<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $usuarios  = User::orderBy('nombre')->paginate(20);
        $zonaHoraria = UsuarioRequest::timezones();
        return view('usuarios.index', compact('usuarios', 'zonaHoraria'));
    }

    public function store(UsuarioRequest $request)
    {
        $validado = $request->validated();

        User::create([
            'nombre'       => $validado['nombre'],
            'password'     => Hash::make($validado['password']),
            'idioma'       => $validado['idioma'],
            'zona_horaria' => $validado['zona_horaria'],
            'tema'         => $validado['tema'],
            'administrador'=> $request->boolean('administrador'),
            'activo'       => true,
        ]);

        Log::info('[UserController] Usuario creado: ' . $validado['nombre']);
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $validado = $request->validated();

        $usuario->fill([
            'nombre'       => $validado['nombre'],
            'idioma'       => $validado['idioma'],
            'zona_horaria' => $validado['zona_horaria'],
            'tema'         => $validado['tema'],
            'administrador'=> $request->boolean('administrador'),
        ]);

        if (!empty($validado['password'])) {
            $usuario->password = Hash::make($validado['password']);
        }

        $usuario->save();

        Log::info('[UserController] Usuario actualizado: ' . $usuario->nombre);
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $nombre = $usuario->nombre;
        $usuario->delete();
        Log::info('[UserController] Usuario eliminado: ' . $nombre);
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$nombre}' eliminado.");
    }

    public function toggle(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes deshabilitarte a ti mismo.');
        }

        $nuevoEstado = !$usuario->activo;
        $usuario->update(['activo' => $nuevoEstado]);
        $estado = $nuevoEstado ? 'habilitado' : 'deshabilitado';
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$usuario->nombre}' {$estado}.");
    }
}
