<?php

namespace App\Http\Controllers;

use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OpenRouterController extends Controller
{
    public function __construct(private OpenRouterService $service) {}

    public function handleRequest(Request $request)
    {
        $prompt = $request->input('prompt', '');
        if (empty($prompt)) {
            return response()->json(['error' => 'Falta el parámetro prompt'], 422);
        }

        try {
            $text = $this->service->generarTexto($prompt);
            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            Log::error('[OpenRouter] Error en handleRequest', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Error generando texto: ' . $e->getMessage()], 500);
        }
    }
}
