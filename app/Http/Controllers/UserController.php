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
        $users     = User::orderBy('name')->paginate(20);
        $timezones = UserRequest::timezones();
        return view('usuarios.index', compact('users', 'timezones'));
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        User::create([
            'name'     => $data['name'],
            'password' => Hash::make($data['password']),
            'language' => $data['language'],
            'timezone' => $data['timezone'],
            'theme'    => $data['theme'],
            'admin'    => $request->boolean('admin'),
            'enabled'  => true,
        ]);

        Log::info('[UserController] Usuario creado: ' . $data['name']);
        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        $user->fill([
            'name'     => $data['name'],
            'language' => $data['language'],
            'timezone' => $data['timezone'],
            'theme'    => $data['theme'],
            'admin'    => $request->boolean('admin'),
        ]);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        Log::info('[UserController] Usuario actualizado: ' . $user->name);
        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $nombre = $user->name;
        $user->delete();
        Log::info('[UserController] Usuario eliminado: ' . $nombre);
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$nombre}' eliminado.");
    }

    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No puedes deshabilitarte a ti mismo.');
        }

        $newState = !$user->enabled;
        $user->update(['enabled' => $newState]);
        $estado = $newState ? 'habilitado' : 'deshabilitado';
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$user->name}' {$estado}.");
    }
}
