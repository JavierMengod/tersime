<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $usuarios  = User::orderBy('name')->paginate(20);
        $zonaHoraria = UserRequest::timezones();
        return view('usuarios.index', compact('usuarios', 'zonaHoraria'));
    }

    public function store(UserRequest $request)
    {
        $validado = $request->validated();

        User::create([
            'name'     => $validado['name'],
            'password' => Hash::make($validado['password']),
            'language' => $validado['language'],
            'timezone' => $validado['timezone'],
            'theme'    => $validado['theme'],
            'admin'    => $request->boolean('admin'),
            'enabled'  => true,
        ]);

        Log::info('[UserController] Usuario creado: ' . $validado['name']);
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UserRequest $request, User $usuario)
    {
        $validado = $request->validated();

        $usuario->fill([
            'name'     => $validado['name'],
            'language' => $validado['language'],
            'timezone' => $validado['timezone'],
            'theme'    => $validado['theme'],
            'admin'    => $request->boolean('admin'),
        ]);

        if (!empty($validado['password'])) {
            $usuario->password = Hash::make($validado['password']);
        }

        $usuario->save();

        Log::info('[UserController] Usuario actualizado: ' . $usuario->name);
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $nombre = $usuario->name;
        $usuario->delete();
        Log::info('[UserController] Usuario eliminado: ' . $nombre);
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$nombre}' eliminado.");
    }

    public function toggle(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes deshabilitarte a ti mismo.');
        }

        $nuevoEstado = !$usuario->enabled;
        $usuario->update(['enabled' => $nuevoEstado]);
        $estado = $nuevoEstado ? 'habilitado' : 'deshabilitado';
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$usuario->name}' {$estado}.");
    }
}
