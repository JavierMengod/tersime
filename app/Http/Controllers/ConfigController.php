<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class ConfigController extends Controller
{
    /**
     * Actualiza las preferencias del usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        // Valida los datos recibidos
        $validated = $request->validate([
            'language' => 'required|string|in:es,en,fr',
            'timezone' => 'required|string',
            'theme' => 'required|string|in:light,dark',
        ]);

        if($request->input('action') === 'reset'){
            $request->theme = 'light';
            $request->language = 'es';
            $request->timezone = 'UTC+01:00';
            $request->offsetUnset('debug_mode');
        }

        $user = auth()->user();

        if ($user) {
            $user->language = $request->language;
            $user->theme = $request->theme;
            $user->timezone = $request->timezone;
            $user->debug_mode = $request->has('debug_mode') ? 1 : 0;
            $user->save();
        }

        return redirect()
            ->back()
            ->with('success', 'Preferencias actualizadas correctamente.');
    }
}
