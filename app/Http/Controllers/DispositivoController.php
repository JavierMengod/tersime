<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Services\InfluxService;
use App\Models\RegistroEstadoDispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DispositivoController extends Controller
{
    public function tiempoReal()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();

        return view('monitorizacion.tiempo-real', compact('dispositivos'));
    }

    public function index(InfluxService $influx)
    {
        $dispositivos        = Auth::user()->dispositivos()->paginate(10);
        $asignados           = Auth::user()->dispositivos()->pluck('influx_tag')->toArray();
        $dispositivosGrafana = array_values(array_diff($influx->listarDispositivos(), $asignados));

        return view('monitorizacion.dispositivos', compact('dispositivos', 'dispositivosGrafana'));
    }

    public function store(Request $request)
    {
        $validado = $request->validate([
            'nombre'     => 'required|string|max:255',
            'influx_tag' => 'required|string|max:255',
        ]);

        $dispositivo = Dispositivo::firstOrCreate(['influx_tag' => $validado['influx_tag']]);

        if (Auth::user()->dispositivos()->where('dispositivos.id', $dispositivo->id)->exists()) {
            return redirect()
                ->route('monitorizacion.dispositivos')
                ->with('error', 'Ya tienes este dispositivo asignado.');
        }

        $ahora = now();

        DB::transaction(function () use ($dispositivo, $validado, $ahora) {
            Auth::user()->dispositivos()->attach($dispositivo->id, [
                'nombre'     => $validado['nombre'],
                'habilitado' => 1,
            ]);

            RegistroEstadoDispositivo::create([
                'user_id'        => Auth::id(),
                'dispositivo_id' => $dispositivo->id,
                'habilitado'     => true,
                'changed_at'     => $ahora,
            ]);
        });

        return redirect()
            ->route('monitorizacion.dispositivos')
            ->with('success', 'Dispositivo creado correctamente.');
    }

    public function update(Request $request, Dispositivo $dispositivo)
    {
        $validado = $request->validate([
            'nombre'     => 'required|string|max:255',
            'influx_tag' => 'required|string|max:255',
        ]);

        $usuario = Auth::user();

        if ($validado['influx_tag'] === $dispositivo->influx_tag) {
            $usuario->dispositivos()->updateExistingPivot($dispositivo->id, ['nombre' => $validado['nombre']]);
        } else {
            $usuario->dispositivos()->detach($dispositivo->id);

            if ($dispositivo->usuarios()->count() === 0) {
                $dispositivo->delete();
            }

            $nuevo = Dispositivo::firstOrCreate(['influx_tag' => $validado['influx_tag']]);
            $usuario->dispositivos()->attach($nuevo->id, ['nombre' => $validado['nombre'], 'habilitado' => 1]);
        }

        return redirect()
            ->route('monitorizacion.dispositivos')
            ->with('success', 'Dispositivo actualizado correctamente.');
    }

    public function toggle(Dispositivo $dispositivo)
    {
        $usuario = Auth::user();
        $pivot   = $usuario->dispositivos()->where('dispositivos.id', $dispositivo->id)->first();

        if (!$pivot) {
            abort(404);
        }

        $habilitado  = (bool) $pivot->pivot->habilitado;
        $nuevoEstado = !$habilitado;
        $ahora       = now();

        DB::transaction(function () use ($usuario, $dispositivo, $nuevoEstado, $ahora) {
            $usuario->dispositivos()->updateExistingPivot($dispositivo->id, ['habilitado' => $nuevoEstado]);

            RegistroEstadoDispositivo::create([
                'user_id'        => $usuario->id,
                'dispositivo_id' => $dispositivo->id,
                'habilitado'     => $nuevoEstado,
                'changed_at'     => $ahora,
            ]);
        });

        $mensaje = $habilitado ? 'Dispositivo deshabilitado.' : 'Dispositivo habilitado.';

        return redirect()->route('monitorizacion.dispositivos')->with('success', $mensaje);
    }

    public function destroy(Dispositivo $dispositivo)
    {
        $usuario = Auth::user();
        $pivot   = $usuario->dispositivos()->where('dispositivos.id', $dispositivo->id)->first();
        $nombre  = $pivot ? $pivot->pivot->nombre : $dispositivo->influx_tag;

        $usuario->dispositivos()->detach($dispositivo->id);

        if ($dispositivo->usuarios()->count() === 0) {
            $dispositivo->delete();
        }

        return redirect()
            ->route('monitorizacion.dispositivos')
            ->with('success', "Dispositivo \"{$nombre}\" eliminado correctamente.");
    }
}
