<?php

namespace App\Http\Controllers;

use App\Http\Requests\RuleRequest;
use App\Models\Rule;
use App\Traits\BuildsRuleAttributes;
use Illuminate\Support\Facades\Log;

class ReglaController extends Controller
{
    use BuildsRuleAttributes;

    public function index()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $reglas       = auth()->user()->rules()->with('dispositivos')->paginate(10);

        return view('alertas.acciones', compact('dispositivos', 'reglas'));
    }

    public function store(RuleRequest $request)
    {
        if (Rule::userHasReachedLimit(auth()->id())) {
            return back()->withErrors(['name' => 'Has alcanzado el límite de 50 reglas.']);
        }

        $validated = $request->validated();

        $rule = Rule::create(array_merge($this->ruleFieldsFrom($validated), [
            'user_id'   => auth()->id(),
            'is_active' => true,
        ]));

        $rule->dispositivos()->sync($validated['devices']);

        Log::info("Regla creada correctamente con ID {$rule->id}.");

        return redirect()->back()->with('success', 'Regla guardada correctamente.');
    }

    public function update(RuleRequest $request, Rule $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $validated = $request->validated();

        $regla->fill($this->ruleFieldsFrom($validated))->save();
        $regla->dispositivos()->sync($validated['devices']);

        Log::info("Regla ID {$regla->id} actualizada correctamente.");

        return redirect()->back()->with('success', 'Regla actualizada correctamente.');
    }

    public function toggle(Rule $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $newState = !$regla->is_active;
        $regla->update(['is_active' => $newState]);

        $action = $newState ? 'activada' : 'desactivada';
        Log::info("Regla ID {$regla->id} {$action}.");

        return back()->with('success', "Regla {$action}.");
    }

    public function destroy(Rule $regla)
    {
        abort_if((int) $regla->user_id !== (int) auth()->id(), 404);

        $regla->delete();

        Log::info("Regla ID {$regla->id} eliminada correctamente.");

        return redirect()->back()->with('success', 'Regla eliminada correctamente.');
    }
}
