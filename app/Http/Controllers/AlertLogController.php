<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertIndexRequest;
use App\Models\AlertLog;

class AlertLogController extends Controller
{
    public function index(AlertIndexRequest $request)
    {
        $user = $request->user();
        $sort = $request->input('sort', 'created_at');
        $dir  = $request->input('dir',  'desc');

        $query = AlertLog::forUser($user->id);

        if ($request->filled('device')) {
            $query->where('device_name', $request->input('device'));
        }
        if ($request->filled('rule')) {
            $query->where('rule_name', $request->input('rule'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs    = $query->orderBy($sort, $dir)->paginate($perPage)->withQueryString();

        $devices = AlertLog::forUser($user->id)
            ->select('device_name')->distinct()->orderBy('device_name')
            ->pluck('device_name');

        $rules = AlertLog::forUser($user->id)
            ->select('rule_name')->distinct()->orderBy('rule_name')
            ->pluck('rule_name');

        return view('alertas.historial', compact('logs', 'devices', 'rules', 'sort', 'dir'));
    }
}
