<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class VerifyDatasourceToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = Setting::get('grafana_renderer_token')
            ?: config('tersime.grafana.renderer_token', env('GRAFANA_RENDERER_TOKEN', ''));

        if (empty($expected)) {
            return $next($request);
        }

        $provided = $request->header('X-Datasource-Token')
            ?? $request->query('datasource_token');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
