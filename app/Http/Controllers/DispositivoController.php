<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DispositivoController extends Controller
{
    public function index()
    {
        $dispositivos        = Auth::user()->dispositivos()->paginate(10);
        $asignados           = Auth::user()->dispositivos()->pluck('influx_tag')->toArray();
        $dispositivosGrafana = array_values(array_diff(GrafanaController::dispositivosGrafana(), $asignados));

        return view('monitorizacion.dispositivos', compact('dispositivos', 'dispositivosGrafana'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'     => 'required|string|max:255',
            'influx_tag' => 'required|string|max:255',
        ]);

        $dispositivo = Dispositivo::firstOrCreate(['influx_tag' => $data['influx_tag']]);

        if (Auth::user()->dispositivos()->where('dispositivos.id', $dispositivo->id)->exists()) {
            return redirect()
                ->route('monitorizacion-dispositivos')
                ->with('error', 'Ya tienes este dispositivo asignado.');
        }

        Auth::user()->dispositivos()->attach($dispositivo->id, ['nombre' => $data['nombre']]);

        return redirect()
            ->route('monitorizacion-dispositivos')
            ->with('success', 'Dispositivo creado correctamente.');
    }

    public function update(Request $request, Dispositivo $dispositivo)
    {
        $data = $request->validate([
            'nombre'     => 'required|string|max:255',
            'influx_tag' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        if ($data['influx_tag'] === $dispositivo->influx_tag) {
            $user->dispositivos()->updateExistingPivot($dispositivo->id, ['nombre' => $data['nombre']]);
        } else {
            $user->dispositivos()->detach($dispositivo->id);

            if ($dispositivo->usuarios()->count() === 0) {
                $dispositivo->delete();
            }

            $nuevo = Dispositivo::firstOrCreate(['influx_tag' => $data['influx_tag']]);
            $user->dispositivos()->attach($nuevo->id, ['nombre' => $data['nombre']]);
        }

        return redirect()
            ->route('monitorizacion-dispositivos')
            ->with('success', 'Dispositivo actualizado correctamente.');
    }

    public function destroy(Dispositivo $dispositivo)
    {
        $user   = Auth::user();
        $pivot  = $user->dispositivos()->where('dispositivos.id', $dispositivo->id)->first();
        $nombre = $pivot ? $pivot->pivot->nombre : $dispositivo->influx_tag;

        $user->dispositivos()->detach($dispositivo->id);

        if ($dispositivo->usuarios()->count() === 0) {
            $dispositivo->delete();
        }

        return redirect()
            ->route('monitorizacion-dispositivos')
            ->with('success', "Dispositivo \"{$nombre}\" eliminado correctamente.");
    }
}
