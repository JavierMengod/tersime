<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AlertLog;

class AlertLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $allowedSorts = ['created_at', 'device_name', 'rule_name', 'type'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'created_at';
        $dir  = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $query = AlertLog::where('user_id', $user->id);

        if ($request->filled('device')) {
            $query->where('device_name', $request->input('device'));
        }
        if ($request->filled('rule')) {
            $query->where('rule_name', $request->input('rule'));
        }
        if ($request->filled('type') && in_array($request->input('type'), ['firing', 'resolution'])) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->orderBy($sort, $dir)->paginate(20)->withQueryString();

        // Single query for both filter dropdowns
        $meta    = AlertLog::where('user_id', $user->id)
            ->selectRaw('device_name, rule_name')
            ->get();
        $devices = $meta->pluck('device_name')->unique()->sort()->values();
        $rules   = $meta->pluck('rule_name')->unique()->sort()->values();

        return view('alertas.historial', compact('logs', 'devices', 'rules', 'sort', 'dir'));
    }
}
