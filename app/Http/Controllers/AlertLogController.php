<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertIndexRequest;
use App\Models\RegistroAlerta;

class AlertLogController extends Controller
{
    public function index(AlertIndexRequest $request)
    {
        $usuario  = $request->user();
        $ordenar  = $request->input('sort', 'created_at');
        $direccion = $request->input('dir',  'desc');

        $consulta = RegistroAlerta::porUsuario($usuario->id);

        if ($request->filled('device')) {
            $consulta->where('nombre_dispositivo', $request->input('device'));
        }
        if ($request->filled('rule')) {
            $consulta->where('nombre_regla', $request->input('rule'));
        }
        if ($request->filled('type')) {
            $consulta->where('tipo', $request->input('type'));
        }
        if ($request->filled('from')) {
            $consulta->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $consulta->whereDate('created_at', '<=', $request->input('to'));
        }

        $porPagina = (int) $request->input('per_page', 20);
        $registros = $consulta->orderBy($ordenar, $direccion)->paginate($porPagina)->withQueryString();

        $dispositivos = RegistroAlerta::porUsuario($usuario->id)
            ->select('nombre_dispositivo')->distinct()->orderBy('nombre_dispositivo')
            ->pluck('nombre_dispositivo');

        $nombresReglas = RegistroAlerta::porUsuario($usuario->id)
            ->select('nombre_regla')->distinct()->orderBy('nombre_regla')
            ->pluck('nombre_regla');

        return view('alertas.historial', compact('registros', 'dispositivos', 'nombresReglas', 'ordenar', 'direccion'));
    }
}
