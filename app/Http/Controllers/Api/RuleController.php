<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReglaRequest;
use App\Http\Resources\RuleResource;
use App\Models\Regla;
use App\Traits\BuildsRuleAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleController extends Controller
{
    use BuildsRuleAttributes;

    public function index(Request $request)
    {
        $reglas = $request->user()->reglas()->with('dispositivos')->get();

        return response()->json(
            $reglas->map(fn($r) => (new RuleResource($r))->toArray($request))
        );
    }

    public function store(ReglaRequest $request)
    {
        if (Regla::limiteAlcanzado($request->user()->id)) {
            return response()->json(['error' => 'Has alcanzado el límite de 50 reglas.'], 422);
        }

        $validado = $request->validated();

        $regla = Regla::create(array_merge($this->camposReglaDesde($validado), [
            'user_id' => $request->user()->id,
            'activo'  => true,
        ]));

        $regla->dispositivos()->sync($validado['devices']);
        $regla->load('dispositivos');

        Log::info('[API] Regla creada: ' . $regla->id);

        return (new RuleResource($regla))->response()->setStatusCode(201);
    }

    public function update(ReglaRequest $request, $id)
    {
        $regla = $request->reglaResuelta() ?? Regla::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validado = $request->validated();

        $regla->fill($this->camposReglaDesde($validado))->save();
        $regla->dispositivos()->sync($validado['devices']);
        $regla->load('dispositivos');

        return new RuleResource($regla);
    }

    public function destroy(Request $request, $id)
    {
        $regla = $this->buscarReglaPropietaria($request, $id);
        $regla->delete();

        return response()->json(['message' => 'Regla eliminada.']);
    }

    public function toggle(Request $request, $id)
    {
        $regla       = $this->buscarReglaPropietaria($request, $id);
        $nuevoEstado = !$regla->activo;
        $regla->update(['activo' => $nuevoEstado]);

        return response()->json([
            'id'     => $regla->id,
            'activo' => $nuevoEstado,
        ]);
    }

    private function buscarReglaPropietaria(Request $request, $id): Regla
    {
        return Regla::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
