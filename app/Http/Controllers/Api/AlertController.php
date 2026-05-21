<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertIndexRequest;
use App\Models\RegistroAlerta;

class AlertController extends Controller
{
    public function index(AlertIndexRequest $request)
    {
        $query = RegistroAlerta::porUsuario($request->user()->id);

        if ($request->filled('device')) {
            $query->where('nombre_dispositivo', $request->input('device'));
        }
        if ($request->filled('rule')) {
            $query->where('nombre_regla', $request->input('rule'));
        }
        if ($request->filled('type')) {
            $query->where('tipo', $request->input('type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs    = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($logs);
    }
}
