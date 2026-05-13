<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\GrafanaController;

class DispositivoController extends Controller
{
    protected GrafanaController $grafana;

    public function index()
    {
        Log::info('Entrando en DispositivoController@index');

        $dispositivos = Auth::user()->dispositivos;
        $dispositivosGrafana = GrafanaController::dispositivosGrafana();

        return view('monitorizacion.dispositivos', compact(
            'dispositivos',
            'dispositivosGrafana'
        ));
    }

    public function store(Request $request)
    {
        Log::info('Entrando en DispositivoController@store', ['request' => $request->all()]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'URL' => 'required|string|max:255',
        ]);

        $dispositivo = Dispositivo::create($data);
        Auth::user()->dispositivos()->attach($dispositivo->id);

        return redirect()
            ->route('dispositivo.index')
            ->with('success', 'Dispositivo creado correctamente.');
    }

    public function update(Request $request)
    {
        Log::info('Entrando en DispositivoController@update', ['request' => $request->all()]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'nombre_original' => 'required|string|max:255',
            'URL' => 'required|string|max:255',
        ]);

        // Buscar el dispositivo por nombre
        $dispositivo = Dispositivo::where('nombre', $data['nombre_original'])->first();

        if (!$dispositivo) {
            Log::warning('Dispositivo no encontrado para actualizar', ['nombre' => $data['nombre']]);
            return redirect()
                ->route('dispositivo.index')
                ->with('error', 'Dispositivo no encontrado.');
        }

        // Actualizar campos
        $dispositivo->update($data);

        return redirect()
            ->route('dispositivo.index')
            ->with('success', 'Dispositivo actualizado correctamente.');
    }


    public function destroy(Dispositivo $dispositivo)
    {
        Log::info('Entrando en DispositivoController@destroy', [
            'id' => $dispositivo->id,
            'nombre' => $dispositivo->nombre
        ]);

        $nombre = $dispositivo->nombre;
        $dispositivo->delete();

        return redirect()
            ->route('dispositivo.index')
            ->with('success', 'Dispositivo "' . $nombre . '" eliminado correctamente.');
    }
}
