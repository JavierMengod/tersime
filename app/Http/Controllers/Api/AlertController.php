<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroAlertasRequest;
use App\Models\RegistroAlerta;

class AlertController extends Controller
{
    public function index(FiltroAlertasRequest $request)
    {
        $consulta = RegistroAlerta::porUsuario($request->user()->id);

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
            $consulta->whereDate('creado_en', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $consulta->whereDate('creado_en', '<=', $request->input('to'));
        }

        $porPagina = (int) $request->input('per_page', 20);
        $registros = $consulta->orderBy('creado_en', 'desc')->paginate($porPagina);

        return response()->json($registros);
    }
}
