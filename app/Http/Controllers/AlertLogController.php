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

        $devices = AlertLog::where('user_id', $user->id)
            ->select('device_name')->distinct()->orderBy('device_name')
            ->pluck('device_name');

        $rules = AlertLog::where('user_id', $user->id)
            ->select('rule_name')->distinct()->orderBy('rule_name')
            ->pluck('rule_name');

        return view('alertas.historial', compact('logs', 'devices', 'rules', 'sort', 'dir'));
    }
}
