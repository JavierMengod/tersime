<?php

namespace App\Http\Controllers;

use App\Http\Requests\RuleRequest;
use App\Models\Regla;
use App\Traits\BuildsRuleAttributes;
use Illuminate\Support\Facades\Log;

class ReglaController extends Controller
{
    use BuildsRuleAttributes;

    public function index()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $reglas       = auth()->user()->reglas()->with('dispositivos')->paginate(10);

        return view('alertas.acciones', compact('dispositivos', 'reglas'));
    }

    public function store(RuleRequest $request)
    {
        if (Regla::limiteAlcanzado(auth()->id())) {
            return back()->withErrors(['name' => 'Has alcanzado el límite de 50 reglas.']);
        }

        $validado = $request->validated();

        $regla = Regla::create(array_merge($this->camposReglaDesde($validado), [
            'user_id' => auth()->id(),
            'activo'  => true,
        ]));

        $regla->dispositivos()->sync($validado['devices']);

        Log::info("Regla creada correctamente con ID {$regla->id}.");

        return redirect()->back()->with('success', 'Regla guardada correctamente.');
    }

    public function update(RuleRequest $request, Regla $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $validado = $request->validated();

        $regla->fill($this->camposReglaDesde($validado))->save();
        $regla->dispositivos()->sync($validado['devices']);

        Log::info("Regla ID {$regla->id} actualizada correctamente.");

        return redirect()->back()->with('success', 'Regla actualizada correctamente.');
    }

    public function toggle(Regla $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $nuevoEstado = !$regla->activo;
        $regla->update(['activo' => $nuevoEstado]);

        $accion = $nuevoEstado ? 'activada' : 'desactivada';
        Log::info("Regla ID {$regla->id} {$accion}.");

        return back()->with('success', "Regla {$accion}.");
    }

    public function destroy(Regla $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $regla->delete();

        Log::info("Regla ID {$regla->id} eliminada correctamente.");

        return redirect()->back()->with('success', 'Regla eliminada correctamente.');
    }
}
