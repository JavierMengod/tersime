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

        try {
            $client = Http::withHeaders($headers)->timeout(30);

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
