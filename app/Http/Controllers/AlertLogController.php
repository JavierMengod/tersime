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

        $consulta = RegistroAlerta::forUser($usuario->id);

        if ($request->filled('device')) {
            $consulta->where('device_name', $request->input('device'));
        }
        if ($request->filled('rule')) {
            $consulta->where('rule_name', $request->input('rule'));
        }
        if ($request->filled('type')) {
            $consulta->where('type', $request->input('type'));
        }
        if ($request->filled('from')) {
            $consulta->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $consulta->whereDate('created_at', '<=', $request->input('to'));
        }

        $porPagina = (int) $request->input('per_page', 20);
        $registros = $consulta->orderBy($ordenar, $direccion)->paginate($porPagina)->withQueryString();

        $dispositivos = RegistroAlerta::forUser($usuario->id)
            ->select('device_name')->distinct()->orderBy('device_name')
            ->pluck('device_name');

        $nombresReglas = RegistroAlerta::forUser($usuario->id)
            ->select('rule_name')->distinct()->orderBy('rule_name')
            ->pluck('rule_name');

        return view('alertas.historial', compact('registros', 'dispositivos', 'nombresReglas', 'ordenar', 'direccion'));
    }
}
