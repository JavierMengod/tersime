<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Services\InfluxService;
use App\Models\DispositivoEstadoLog;
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
        $data = $request->validate([
            'nombre'     => 'required|string|max:255',
            'influx_tag' => 'required|string|max:255',
        ]);

        $dispositivo = Dispositivo::firstOrCreate(['influx_tag' => $data['influx_tag']]);

        if (Auth::user()->dispositivos()->where('dispositivos.id', $dispositivo->id)->exists()) {
            return redirect()
                ->route('monitorizacion.dispositivos')
                ->with('error', 'Ya tienes este dispositivo asignado.');
        }

        $now = now();

        DB::transaction(function () use ($dispositivo, $data, $now) {
            Auth::user()->dispositivos()->attach($dispositivo->id, [
                'nombre'     => $data['nombre'],
                'habilitado' => 1,
            ]);

            DispositivoEstadoLog::create([
                'user_id'        => Auth::id(),
                'dispositivo_id' => $dispositivo->id,
                'habilitado'     => true,
                'changed_at'     => $now,
            ]);
        });

        return redirect()
            ->route('monitorizacion.dispositivos')
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
            $user->dispositivos()->attach($nuevo->id, ['nombre' => $data['nombre'], 'habilitado' => 1]);
        }

        return redirect()
            ->route('monitorizacion.dispositivos')
            ->with('success', 'Dispositivo actualizado correctamente.');
    }

    public function toggle(Dispositivo $dispositivo)
    {
        $user  = Auth::user();
        $pivot = $user->dispositivos()->where('dispositivos.id', $dispositivo->id)->first();

        if (!$pivot) {
            abort(404);
        }

        $habilitado    = (bool) $pivot->pivot->habilitado;
        $nuevoEstado   = !$habilitado;
        $now           = now();

        DB::transaction(function () use ($user, $dispositivo, $nuevoEstado, $now) {
            $user->dispositivos()->updateExistingPivot($dispositivo->id, ['habilitado' => $nuevoEstado]);

            DispositivoEstadoLog::create([
                'user_id'        => $user->id,
                'dispositivo_id' => $dispositivo->id,
                'habilitado'     => $nuevoEstado,
                'changed_at'     => $now,
            ]);
        });

        $msg = $habilitado ? 'Dispositivo deshabilitado.' : 'Dispositivo habilitado.';

        return redirect()->route('monitorizacion.dispositivos')->with('success', $msg);
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
            ->route('monitorizacion.dispositivos')
            ->with('success', "Dispositivo \"{$nombre}\" eliminado correctamente.");
    }
}
