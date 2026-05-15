<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = $request->user()
            ->rules()
            ->with('dispositivos')
            ->get()
            ->map(function ($r) {
                return $this->format($r);
            });

        return response()->json($rules);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'devices'            => 'required|array|min:1',
            'devices.*'          => 'integer|exists:dispositivos,id',
            'operator'           => 'required|in:>,<,==,!=,>=,<=',
            'value'              => 'required|numeric',
            'for_duration'       => 'required|integer|min:0',
            'methods'            => 'nullable|array',
            'methods.*'          => 'in:telegram,email,discord',
            'template_telegram'  => 'nullable|string',
            'template_email'     => 'nullable|string',
            'template_discord'   => 'nullable|string',
            'recipient_email'    => 'nullable|email',
        ]);

        $methods = $data['methods'] ?? [];

        $rule = Rule::create([
            'name'               => $data['name'],
            'user_id'            => $request->user()->id,
            'operator'           => $data['operator'],
            'comparison_value'   => $data['value'],
            'for_duration'       => $data['for_duration'],
            'time_range'         => 0,
            'is_active'          => true,
            'email_enabled'      => in_array('email', $methods, true),
            'telegram_enabled'   => in_array('telegram', $methods, true),
            'discord_enabled'    => in_array('discord', $methods, true),
            'template_telegram'  => $data['template_telegram'] ?? null,
            'template_email'     => $data['template_email'] ?? null,
            'template_discord'   => $data['template_discord'] ?? null,
            'recipient_email'    => $data['recipient_email'] ?? null,
        ]);

        $rule->dispositivos()->sync($data['devices']);
        $rule->load('dispositivos');

        Log::info('[API] Regla creada: ' . $rule->id);

        return response()->json($this->format($rule), 201);
    }

    public function update(Request $request, $id)
    {
        $rule = Rule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'devices'            => 'required|array|min:1',
            'devices.*'          => 'integer|exists:dispositivos,id',
            'operator'           => 'required|in:>,<,==,!=,>=,<=',
            'value'              => 'required|numeric',
            'for_duration'       => 'required|integer|min:0',
            'methods'            => 'nullable|array',
            'methods.*'          => 'in:telegram,email,discord',
            'template_telegram'  => 'nullable|string',
            'template_email'     => 'nullable|string',
            'template_discord'   => 'nullable|string',
            'recipient_email'    => 'nullable|email',
        ]);

        $methods = $data['methods'] ?? [];

        $rule->fill([
            'name'               => $data['name'],
            'operator'           => $data['operator'],
            'comparison_value'   => $data['value'],
            'for_duration'       => $data['for_duration'],
            'email_enabled'      => in_array('email', $methods, true),
            'telegram_enabled'   => in_array('telegram', $methods, true),
            'discord_enabled'    => in_array('discord', $methods, true),
            'template_telegram'  => $data['template_telegram'] ?? null,
            'template_email'     => $data['template_email'] ?? null,
            'template_discord'   => $data['template_discord'] ?? null,
            'recipient_email'    => $data['recipient_email'] ?? null,
        ]);

        $rule->save();
        $rule->dispositivos()->sync($data['devices']);
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

        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'id'        => $rule->id,
            'is_active' => $rule->is_active,
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
            'devices'           => $r->dispositivos->map(function ($d) {
                return ['id' => $d->id, 'influx_tag' => $d->influx_tag, 'nombre' => $d->nombre];
            }),
            'created_at'        => $r->created_at,
            'updated_at'        => $r->updated_at,
        ];
    }
}
