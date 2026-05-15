<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private static array $timezones = [
        'Europe/Madrid'    => 'Europe/Madrid (ES)',
        'Europe/London'    => 'Europe/London (UK)',
        'Europe/Paris'     => 'Europe/Paris (FR)',
        'Europe/Berlin'    => 'Europe/Berlin (DE)',
        'America/New_York' => 'America/New_York (US East)',
        'America/Chicago'  => 'America/Chicago (US Central)',
        'America/Denver'   => 'America/Denver (US Mountain)',
        'America/Los_Angeles' => 'America/Los_Angeles (US West)',
        'America/Sao_Paulo'   => 'America/Sao_Paulo (BR)',
        'Asia/Tokyo'       => 'Asia/Tokyo (JP)',
        'Asia/Shanghai'    => 'Asia/Shanghai (CN)',
        'UTC'              => 'UTC',
    ];

    public function index()
    {
        $users     = User::orderBy('name')->paginate(20);
        $timezones = self::$timezones;
        return view('usuarios.index', compact('users', 'timezones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255|unique:users,name',
            'password'              => 'required|string|min:6|confirmed',
            'language'              => 'required|in:es,en,fr',
            'timezone'              => 'required|string|in:' . implode(',', array_keys(self::$timezones)),
            'theme'                 => 'required|in:light,dark',
            'admin'                 => 'sometimes|boolean',
        ]);

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

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255|unique:users,name,' . $user->id,
            'language' => 'required|in:es,en,fr',
            'timezone' => 'required|string|in:' . implode(',', array_keys(self::$timezones)),
            'theme'    => 'required|in:light,dark',
            'admin'    => 'sometimes|boolean',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user->name     = $data['name'];
        $user->language = $data['language'];
        $user->timezone = $data['timezone'];
        $user->theme    = $data['theme'];
        $user->admin    = $request->boolean('admin');

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

        $user->update(['enabled' => !$user->enabled]);
        $estado = $user->enabled ? 'habilitado' : 'deshabilitado';
        return redirect()->route('usuarios.index')->with('success', "Usuario '{$user->name}' {$estado}.");
    }
}
