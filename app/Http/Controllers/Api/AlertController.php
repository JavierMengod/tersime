<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertIndexRequest;
use App\Models\AlertLog;

class AlertController extends Controller
{
    public function index(AlertIndexRequest $request)
    {
        $query = AlertLog::forUser($request->user()->id);

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
        $logs    = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($logs);
    }
}
