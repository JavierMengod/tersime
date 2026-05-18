<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RuleRequest;
use App\Models\Rule;
use App\Traits\BuildsRuleAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleController extends Controller
{
    use BuildsRuleAttributes;

    public function index(Request $request)
    {
        $rules = $request->user()
            ->rules()
            ->with('dispositivos')
            ->get()
            ->map(fn($r) => $this->format($r));

        return response()->json($rules);
    }

    public function store(RuleRequest $request)
    {
        if ($request->user()->rules()->count() >= 50) {
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

        return response()->json($this->format($rule), 201);
    }

    public function update(RuleRequest $request, $id)
    {
        $rule = Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validated();

        $rule->fill($this->ruleFieldsFrom($validated))->save();
        $rule->dispositivos()->sync($validated['devices']);
        $rule->load('dispositivos');

        return response()->json($this->format($rule));
    }

    public function destroy(Request $request, $id)
    {
        $rule = Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $rule->delete();

        return response()->json(['message' => 'Regla eliminada.']);
    }

    public function toggle(Request $request, $id)
    {
        $rule = Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $newState = !$rule->is_active;
        $rule->update(['is_active' => $newState]);

        return response()->json([
            'id'        => $rule->id,
            'is_active' => $newState,
        ]);
    }

    private function format(Rule $r): array
    {
        return [
            'id'                => $r->id,
            'name'              => $r->name,
            'operator'          => $r->operator,
            'value'             => $r->comparison_value,
            'for_duration'      => $r->for_duration,
            'is_active'         => (bool) $r->is_active,
            'email_enabled'     => (bool) $r->email_enabled,
            'telegram_enabled'  => (bool) $r->telegram_enabled,
            'discord_enabled'   => (bool) $r->discord_enabled,
            'recipient_email'   => $r->recipient_email,
            'last_triggered_at' => $r->last_triggered_at,
            'devices'           => $r->dispositivos->map(fn($d) => [
                'id'        => $d->id,
                'nombre'    => $d->nombre,
            ]),
            'created_at'        => $r->created_at,
            'updated_at'        => $r->updated_at,
        ];
    }
}
