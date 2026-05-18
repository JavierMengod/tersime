<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RuleRequest;
use App\Http\Resources\RuleResource;
use App\Models\Rule;
use App\Traits\BuildsRuleAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleController extends Controller
{
    use BuildsRuleAttributes;

    public function index(Request $request)
    {
        $rules = $request->user()->rules()->with('dispositivos')->get();

        return response()->json(
            $rules->map(fn($r) => (new RuleResource($r))->toArray($request))
        );
    }

    public function store(RuleRequest $request)
    {
        if (Rule::userHasReachedLimit($request->user()->id)) {
            return response()->json(['error' => 'Has alcanzado el límite de 50 reglas.'], 422);
        }

        $validated = $request->validated();

        $rule = Rule::create(array_merge($this->ruleFieldsFrom($validated), [
            'user_id'   => $request->user()->id,
            'is_active' => true,
        ]));

        $rule->dispositivos()->sync($validated['devices']);
        $rule->load('dispositivos');

        Log::info('[API] Regla creada: ' . $rule->id);

        return (new RuleResource($rule))->response()->setStatusCode(201);
    }

    public function update(RuleRequest $request, $id)
    {
        $rule = $request->resolvedRule() ?? Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validated();

        $rule->fill($this->ruleFieldsFrom($validated))->save();
        $rule->dispositivos()->sync($validated['devices']);
        $rule->load('dispositivos');

        return new RuleResource($rule);
    }

    public function destroy(Request $request, $id)
    {
        $rule = $this->findOwnedRule($request, $id);
        $rule->delete();

        return response()->json(['message' => 'Regla eliminada.']);
    }

    public function toggle(Request $request, $id)
    {
        $rule     = $this->findOwnedRule($request, $id);
        $newState = !$rule->is_active;
        $rule->update(['is_active' => $newState]);

        return response()->json([
            'id'        => $rule->id,
            'is_active' => $newState,
        ]);
    }

    private function findOwnedRule(Request $request, $id): Rule
    {
        return Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
