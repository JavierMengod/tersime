<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private string $apiBase = 'https://openrouter.ai/api/v1';

    public function generarPrompt(string $nombreVista, array $data = []): string
    {
        return trim(view('prompts.' . $nombreVista, $data)->render());
    }

    public function buildPrompt(
        string $nombreVista,
        array $datos,
        array $anomalias,
        array $estimacionCoste,
        array $resumenPorDispositivo,
        array $contexto = []
    ): string {
        return $this->generarPrompt($nombreVista, array_merge([
            'datos'                 => $datos,
            'anomalias'             => $anomalias,
            'estimacionCoste'       => $estimacionCoste,
            'resumenPorDispositivo' => $resumenPorDispositivo,
        ], $contexto));
    }

    public function generarTextos(array $prompts): array
    {
        [$apiKey, $model] = $this->resolveCredentials();

        Log::info('[OpenRouter] Generando ' . count($prompts) . ' textos con reintentos');

        $resultados = [];
        foreach ($prompts as $key => $prompt) {
            $resultados[$key] = $this->generarConReintento($key, $prompt, $apiKey, $model);
        }

        return $resultados;
    }

    public function generarTexto(string $prompt, ?array $options = null): string
    {
        [$apiKey, $model] = $this->resolveCredentials();

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

    private function resolveCredentials(): array
    {
        $apiKey = Setting::get('openrouter_api_key') ?: config('tersime.openrouter.api_key');
        $model  = Setting::get('openrouter_model')  ?: config('tersime.openrouter.model');

        if (empty($apiKey) || empty($model)) {
            Log::error('[OpenRouter] Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
            throw new \RuntimeException('Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
        }

        return [$apiKey, $model];
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
                    Log::warning("[OpenRouter] HTTP {$status} en '{$label}', reintentando en 3s (intento {$intento}/{$maxIntentos})");
                    sleep(3);
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

    private function parsearRespuesta($response, string $label): string
    {
        if ($response->serverError() || $response->clientError()) {
            Log::error("[OpenRouter] HTTP error para '{$label}'", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception("Error HTTP {$response->status()} en OpenRouter para '{$label}'");
        }

        $json  = $response->json();
        $texto = $json['choices'][0]['message']['content']
              ?? $json['choices'][0]['text']
              ?? null;

        if ($texto === null) {
            Log::warning("[OpenRouter] Respuesta inesperada para '{$label}'", ['body' => substr($response->body(), 0, 500)]);
            return '';
        }

        return $this->limpiarMarkdown(trim($texto));
    }

    private function limpiarMarkdown(string $texto): string
    {
        $texto = preg_replace('/```[a-z]*\n?.*?```/si', '', $texto);
        $texto = preg_replace('/^#{1,6}\s+/m', '', $texto);
        $texto = preg_replace('/\*{2}(.+?)\*{2}/s', '$1', $texto);
        $texto = preg_replace('/_{2}(.+?)_{2}/s', '$1', $texto);
        $texto = preg_replace('/\*(.+?)\*/s', '$1', $texto);
        $texto = preg_replace('/_(.+?)_/s', '$1', $texto);
        $texto = preg_replace('/^[\*\-]\s+/m', '', $texto);
        $texto = preg_replace('/^\d+\.\s+/m', '', $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }
}
