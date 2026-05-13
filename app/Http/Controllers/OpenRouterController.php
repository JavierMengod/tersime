<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterController extends Controller
{
    /**
     * Base URL de OpenRouter
     */
    protected string $apiBase = 'https://openrouter.ai/api/v1';

    /**
     * Renderiza una plantilla Blade desde resources/prompts/*.blade.php
     */
    public function generarPrompt(string $nombreVista, array $data = []): string
    {
        return trim(view('prompts.' . $nombreVista, $data)->render());
    }

    /**
     * Ejemplo: prompt "resumen"
     */
    public function resumen(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        // Carga resources/prompts/resumen.blade.php
        $prompt = $this->generarPrompt('resumen', [
            'datos' => $datos,
            'anomalias' => $anomalias,
            'estimacionCoste' => $estimacionCoste,
            'resumenPorDispositivo' => $resumenPorDispositivo
        ]);

        return $this->generarTexto($prompt);
    }

    public function conclusion(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        // Carga resources/prompts/resumen.blade.php
        $prompt = $this->generarPrompt('conclusion', [
            'datos' => $datos,
            'anomalias' => $anomalias,
            'estimacionCoste' => $estimacionCoste,
            'resumenPorDispositivo' => $resumenPorDispositivo
        ]);

        return $this->generarTexto($prompt);
    }

    public function distribucionHorariaTextual(array $datos = [], array $anomalias = [], array $estimacionCoste = [], array $resumenPorDispositivo = []): string
    {
        // Carga resources/prompts/resumen.blade.php
        $prompt = $this->generarPrompt('distribucionHoraria', [
            'datos' => $datos,
            'anomalias' => $anomalias,
            'estimacionCoste' => $estimacionCoste,
            'resumenPorDispositivo' => $resumenPorDispositivo
        ]);

        return $this->generarTexto($prompt);
    }
    /**
     * Función que encapsula la llamada a OpenRouter.
     */
    public function generarTexto(string $prompt, ?array $options = null): string
    {
        $apiKey = env('OPENROUTER_API_KEY');
        $model = env('OPENROUTER_MODEL');

        if (empty($apiKey) || empty($model)) {
            Log::error('OpenRouter: faltan variables de entorno (OPENROUTER_API_KEY / OPENROUTER_MODEL)');
            throw new \RuntimeException('Faltan OPENROUTER_API_KEY o OPENROUTER_MODEL en .env');
        }

        // Payload por defecto
        $payload = array_merge([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ], $options ?? []);

        // Llamada HTTP
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(120)
            ->post($this->apiBase . '/chat/completions', $payload);

        // Log y manejo de errores HTTP
        if ($response->serverError() || $response->clientError()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Error en la petición a OpenRouter: HTTP ' . $response->status());
        }

        $json = $response->json();

        // 1) Formato típico
        if (isset($json['choices'][0]['message']['content'])) {
            return trim($json['choices'][0]['message']['content']);
        }

        // 2) Alternativa: text
        if (isset($json['choices'][0]['text'])) {
            return trim($json['choices'][0]['text']);
        }

        // 3) Formatos diferentes
        if (isset($json['output']) && is_array($json['output'])) {
            $pieces = [];
            foreach ($json['output'] as $o) {
                if (is_string($o)) {
                    $pieces[] = $o;
                } elseif (is_array($o) && isset($o['content'])) {
                    $pieces[] = is_string($o['content']) ? $o['content'] : json_encode($o['content']);
                } else {
                    $pieces[] = json_encode($o);
                }
            }
            return trim(implode("\n\n", $pieces));
        }

        // 4) Fallback
        $body = $response->body();
        Log::warning('OpenRouter: respuesta no estándar', ['body' => substr($body, 0, 1000)]);
        return trim($body);
    }

    /**
     * Endpoint API opcional
     */
    public function handleRequest(Request $request)
    {
        $prompt = $request->input('prompt', '');
        if (empty($prompt)) {
            return response()->json(['error' => 'Falta el parámetro prompt'], 422);
        }

        try {
            // Ahora sí usa la función correcta
            $text = $this->generarTexto($prompt);
            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('OpenRouter controller error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error generando texto: ' . $e->getMessage()], 500);
        }
    }
}
