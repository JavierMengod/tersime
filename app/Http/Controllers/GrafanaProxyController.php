<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PrediccionController;
use App\Models\Ajuste;
use App\Services\InfluxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrafanaProxyController extends Controller
{
    public function proxy(Request $request, string $ruta = '')
    {
        $base     = Ajuste::get('grafana_base_url') ?: config('app.grafana_base_url', 'http://localhost:3000');
        $destino  = rtrim($base, '/') . '/' . ltrim($ruta, '/');

        $qs = $request->getQueryString();
        if ($qs) {
            $destino .= '?' . $qs;
        }

        $metodo      = strtolower($request->method());
        $tipoContenido = $request->header('Content-Type');

        $cabeceras = [
            'X-WEBAUTH-USER' => auth()->user()->nombre,
            'Accept'         => $request->header('Accept', '*/*'),
        ];
        if ($tipoContenido) {
            $cabeceras['Content-Type'] = $tipoContenido;
        }

        $cookiesGrafana = collect($request->cookies->all())
            ->filter(fn($v, $k) => str_starts_with($k, 'grafana_'))
            ->map(fn($v, $k) => $k . '=' . $v)
            ->join('; ');
        if ($cookiesGrafana) {
            $cabeceras['Cookie'] = $cookiesGrafana;
        }

        // Con ENABLE_LOGIN_TOKEN=false Grafana no rota tokens, pero su JS llama
        // este endpoint igualmente; responder 401 provoca un bucle de recargas.
        if ($ruta === 'api/user/auth-tokens/rotate') {
            return response()->json(['message' => 'Token rotated']);
        }

        // Evita un deadlock: si Grafana proxiara /prediccion/obtener, el worker PHP
        // quedaría bloqueado esperando a Grafana, que a su vez espera a PHP.
        // Servir la petición en el mismo proceso corta el ciclo.
        if (str_contains($ruta, 'datasources/proxy') && str_contains($ruta, 'prediccion/obtener')) {
            return app(PrediccionController::class)
                ->obtenerDatos($request, app(InfluxService::class));
        }

        $esPrediccion = str_contains($ruta, 'prediccion/obtener') || str_contains($ruta, 'prediction');
        $timeout = $esPrediccion
            ? ((int) (Ajuste::get('predictor_timeout') ?: 120)) + 60
            : 30;

        try {
            $cliente = Http::withHeaders($cabeceras)->timeout($timeout);

            if (in_array($metodo, ['post', 'put', 'patch'])) {
                $respuesta = $cliente
                    ->withBody($request->getContent(), $tipoContenido ?: 'application/json')
                    ->{$metodo}($destino);
            } else {
                $respuesta = $cliente->{$metodo}($destino);
            }

            $tipoCont = $respuesta->header('Content-Type', 'application/octet-stream');
            $estado   = $respuesta->status();

            $cabOut = ['Content-Type' => $tipoCont];
            foreach (['Cache-Control', 'ETag', 'Last-Modified', 'Expires'] as $h) {
                $v = $respuesta->header($h);
                if ($v) {
                    $cabOut[$h] = $v;
                }
            }
            // X-Frame-Options y Content-Security-Policy se omiten deliberadamente
            // para que los paneles carguen dentro de iframes en esta aplicación.

            $respuestaLaravel = response($respuesta->body(), $estado)->withHeaders($cabOut);

            // Headers Set-Cookie en crudo para evitar incompatibilidad Guzzle↔Symfony.
            foreach ($respuesta->toPsrResponse()->getHeader('Set-Cookie') as $raw) {
                $respuestaLaravel->headers->set('Set-Cookie', $raw, false);
            }

            return $respuestaLaravel;

        } catch (\Throwable $e) {
            Log::error('[GrafanaProxy] ' . $e->getMessage(), ['upstream' => $destino]);
            return response('Proxy error: could not reach Grafana', 502);
        }
    }
}
