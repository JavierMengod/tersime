<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterController extends Controller
{
    protected string $apiBase = 'https://openrouter.ai/api/v1';

    /**
     * Renderiza una plantilla Blade desde resources/prompts/*.blade.php
     */
    public function generarPrompt(string $nombreVista, array $data = []): string
    {
        return trim(view('prompts.' . $nombreVista, $data)->render());
    }

    /**
     * Construye el prompt sin hacer la llamada HTTP.
     * Usado por generarTextoParalelo() para disparar todas las peticiones a la vez.
     */
    public function buildPrompt(
        string $nombreVista,
        array $datos,
        array $anomalias,
        array $estimacionCoste,
        array $resumenPorDispositivo
    ): string {
        return $this->generarPrompt($nombreVista, [
            'datos'                 => $datos,
            'anomalias'             => $anomalias,
            'estimacionCoste'       => $estimacionCoste,
            'resumenPorDispositivo' => $resumenPorDispositivo,
        ]);
    }

    /**
     * Genera texto para varios prompts de forma secuencial con reintentos.
     * Usa reintentos automáticos en 429/502 para tolerar rate limits de modelos gratuitos.
     *
     * @param array<string,string> $prompts  ['resumen' => '...', 'conclusion' => '...', ...]
     * @return array<string,string>
     */
    public function generarTextoParalelo(array $prompts): array
    {
        $apiKey = Setting::get('openrouter_api_key') ?: config('tersime.openrouter.api_key');
        $model  = Setting::get('openrouter_model')  ?: config('tersime.openrouter.model');

        if (empty($apiKey) || empty($model)) {
            Log::error('[OpenRouter] Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
            throw new \RuntimeException('Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
        }

        Log::info('[OpenRouter] Generando ' . count($prompts) . ' textos con reintentos');

        $resultados = [];
        foreach ($prompts as $key => $prompt) {
            $resultados[$key] = $this->generarConReintento($key, $prompt, $apiKey, $model);
        }

        return $resultados;
    }

    private function generarConReintento(string $label, string $prompt, string $apiKey, string $model, int $maxIntentos = 3): string
    {
        $url = $this->apiBase . '/chat/completions';

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                ])->timeout(30)->post($url, [
                    'model'    => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

                $status = $response->status();

                if ($response->successful()) {
                    return $this->parsearRespuesta($response, $label);
                }

                if (in_array($status, [429, 502, 503]) && $intento < $maxIntentos) {
                    $espera = 3;
                    Log::warning("[OpenRouter] HTTP {$status} en '{$label}', reintentando en {$espera}s (intento {$intento}/{$maxIntentos})");
                    sleep($espera);
                    continue;
                }

                Log::error("[OpenRouter] HTTP error para '{$label}'", [
                    'status' => $status,
                    'body'   => $response->body(),
                ]);
                return '';

            } catch (\Throwable $e) {
                if ($intento < $maxIntentos) {
                    Log::warning("[OpenRouter] Excepción en '{$label}', reintentando", ['error' => $e->getMessage()]);
                    sleep(3);
                    continue;
                }
                Log::error("[OpenRouter] Fallo definitivo en '{$label}'", ['error' => $e->getMessage()]);
                return '';
            }
        }

        return '';
    }

    public function resumen(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        $prompt = $this->generarPrompt('resumen', compact('datos', 'anomalias', 'estimacionCoste', 'resumenPorDispositivo'));
        return $this->generarTexto($prompt);
    }

    public function conclusion(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        $prompt = $this->generarPrompt('conclusion', compact('datos', 'anomalias', 'estimacionCoste', 'resumenPorDispositivo'));
        return $this->generarTexto($prompt);
    }

    public function distribucionHorariaTextual(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        $prompt = $this->generarPrompt('distribucionHoraria', compact('datos', 'anomalias', 'estimacionCoste', 'resumenPorDispositivo'));
        return $this->generarTexto($prompt);
    }

    public function generarTexto(string $prompt, ?array $options = null): string
    {
        $apiKey = Setting::get('openrouter_api_key') ?: config('tersime.openrouter.api_key');
        $model  = Setting::get('openrouter_model')  ?: config('tersime.openrouter.model');

        if (empty($apiKey) || empty($model)) {
            Log::error('[OpenRouter] Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
            throw new \RuntimeException('Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
        }

        $payload = array_merge([
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ], $options ?? []);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post($this->apiBase . '/chat/completions', $payload);

        return $this->parsearRespuesta($response, 'individual');
    }

    /**
     * Endpoint API opcional para llamadas manuales.
     */
    public function handleRequest(Request $request)
    {
        $prompt = $request->input('prompt', '');
        if (empty($prompt)) {
            return response()->json(['error' => 'Falta el parámetro prompt'], 422);
        }

        try {
            $text = $this->generarTexto($prompt);
            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('[Anthropic] Error en handleRequest', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error generando texto: ' . $e->getMessage()], 500);
        }
    }

    private function parsearRespuesta($response, string $label): string
    {
        if ($response instanceof \Throwable) {
            Log::error("[OpenRouter] Excepción en pool para '{$label}'", ['error' => $response->getMessage()]);
            throw $response;
        }

        if ($response->serverError() || $response->clientError()) {
            Log::error("[OpenRouter] HTTP error para '{$label}'", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception("Error HTTP {$response->status()} en OpenRouter para '{$label}'");
        }

        $json = $response->json();

        $texto = null;
        if (isset($json['choices'][0]['message']['content'])) {
            $texto = $json['choices'][0]['message']['content'];
        } elseif (isset($json['choices'][0]['text'])) {
            $texto = $json['choices'][0]['text'];
        }

        if ($texto === null) {
            Log::warning("[OpenRouter] Respuesta inesperada para '{$label}'", ['body' => substr($response->body(), 0, 500)]);
            return '';
        }

        return $this->limpiarMarkdown(trim($texto));
    }

    private function limpiarMarkdown(string $texto): string
    {
        // Eliminar bloques de código
        $texto = preg_replace('/```[a-z]*\n?.*?```/si', '', $texto);
        // Eliminar encabezados markdown (## Título)
        $texto = preg_replace('/^#{1,6}\s+/m', '', $texto);
        // Eliminar **negrita** y __negrita__
        $texto = preg_replace('/\*{2}(.+?)\*{2}/s', '$1', $texto);
        $texto = preg_replace('/_{2}(.+?)_{2}/s', '$1', $texto);
        // Eliminar *cursiva* y _cursiva_
        $texto = preg_replace('/\*(.+?)\*/s', '$1', $texto);
        $texto = preg_replace('/_(.+?)_/s', '$1', $texto);
        // Limpiar listas markdown (- item, * item, 1. item)
        $texto = preg_replace('/^[\*\-]\s+/m', '', $texto);
        $texto = preg_replace('/^\d+\.\s+/m', '', $texto);
        // Colapsar múltiples líneas en blanco
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }
}
