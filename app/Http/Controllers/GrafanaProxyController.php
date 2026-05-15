<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrafanaProxyController extends Controller
{
    public function proxy(Request $request, string $path = '')
    {
        $base     = Setting::get('grafana_base_url') ?: config('app.grafana_base_url', 'http://localhost:3000');
        $upstream = rtrim($base, '/') . '/' . ltrim($path, '/');

        $qs = $request->getQueryString();
        if ($qs) {
            $upstream .= '?' . $qs;
        }

        $method      = strtolower($request->method());
        $contentType = $request->header('Content-Type');

        $headers = [
            'X-WEBAUTH-USER' => auth()->user()->email,
            'Accept'         => $request->header('Accept', '*/*'),
        ];
        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }

        // Datasource proxy requests for the prediction endpoint create a deadlock:
        // GrafanaProxy holds the single PHP worker while waiting for Grafana, and
        // Grafana calls back to the same PHP server for /prediccion/obtener.
        // Serve these in-process to break the loop entirely.
        if (str_contains($path, 'datasources/proxy') && str_contains($path, 'prediccion/obtener')) {
            return app(PrediccionController::class)
                ->obtenerDatos($request, app(InfluxController::class));
        }

        // Prediction requests drive a slow Python Prophet service; grant extra time.
        $isPrediccion = str_contains($path, 'prediccion/obtener') || str_contains($path, 'prediction');
        $timeout = $isPrediccion
            ? ((int) (Setting::get('predictor_timeout') ?: 120)) + 60
            : 30;

        try {
            $client = Http::withHeaders($headers)->timeout($timeout);

            if (in_array($method, ['post', 'put', 'patch'])) {
                $response = $client
                    ->withBody($request->getContent(), $contentType ?: 'application/json')
                    ->{$method}($upstream);
            } else {
                $response = $client->{$method}($upstream);
            }

            $respType = $response->header('Content-Type', 'application/octet-stream');
            $status   = $response->status();

            $out = ['Content-Type' => $respType];
            foreach (['Cache-Control', 'ETag', 'Last-Modified', 'Expires'] as $h) {
                $v = $response->header($h);
                if ($v) {
                    $out[$h] = $v;
                }
            }
            // X-Frame-Options y Content-Security-Policy se omiten deliberadamente
            // para que los paneles carguen dentro de iframes en esta aplicación.

            return response($response->body(), $status)->withHeaders($out);

        } catch (\Throwable $e) {
            Log::error('[GrafanaProxy] ' . $e->getMessage(), ['upstream' => $upstream]);
            return response('Proxy error: could not reach Grafana', 502);
        }
    }
}
