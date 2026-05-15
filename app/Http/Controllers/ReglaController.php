<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;
use App\Models\Rule;
use Illuminate\Support\Facades\Log;

class ReglaController extends Controller
{
    public function index()
    {
        $dispositivos = auth()->user()->dispositivos()->wherePivot('habilitado', 1)->get();
        $reglas       = auth()->user()->rules()->with('dispositivos')->paginate(10);

        return view('alertas.acciones', compact('dispositivos', 'reglas'));
    }

    public function store(Request $request)
    {
        Log::debug('Iniciando guardado de reglas');
        Log::debug('Datos recibidos en la request:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'devices' => 'required|array|min:1',
            'devices.*' => 'integer|exists:dispositivos,id',
            'operator' => 'required|in:>,<,==,!=,>=,<=',
            'value' => 'required|numeric',
            'for_duration' => 'required|integer|min:0',
            'methods' => 'nullable|array',
            'methods.*' => 'in:telegram,email,discord',
            'template_telegram' => 'nullable|string',
            'template_email' => 'nullable|string',
            'template_discord' => 'nullable|string',
            'recipient_email' => 'nullable|email',
        ]);

        $methods = $validated['methods'] ?? [];

        $rule = Rule::create([
            'name' => $validated['name'],
            'user_id' => auth()->id(),
            'operator' => $validated['operator'],
            'comparison_value' => $validated['value'],
            'for_duration' => $validated['for_duration'],
            'time_range' => 0,
            'is_active' => true,
            'email_enabled' => in_array('email', $methods, true),
            'telegram_enabled' => in_array('telegram', $methods, true),
            'discord_enabled' => in_array('discord', $methods, true),
            'template_telegram' => $request->input('template_telegram'),
            'template_email' => $request->input('template_email'),
            'template_discord' => $request->input('template_discord'),
            'recipient_email' => $validated['recipient_email'] ?? null,
        ]);

        // Asocia múltiples dispositivos usando sync
        $rule->dispositivos()->sync($validated['devices']);

        Log::info("Regla creada correctamente con ID {$rule->id} y dispositivos asociados.");

        return redirect()->back()->with('success', 'Regla(s) guardada(s) correctamente.');
    }

    public function update(Request $request, $id)
    {
        Log::debug("Iniciando actualización de regla ID {$id}");
        Log::debug('Datos recibidos en la request:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'devices' => 'required|array|min:1',
            'devices.*' => 'integer|exists:dispositivos,id',
            'operator' => 'required|in:>,<,==,!=,>=,<=',
            'value' => 'required|numeric',
            'for_duration' => 'required|integer|min:0',
            'methods' => 'nullable|array',
            'methods.*' => 'in:telegram,email,discord',
            'template_telegram' => 'nullable|string',
            'template_email' => 'nullable|string',
            'template_discord' => 'nullable|string',
            'recipient_email' => 'nullable|email',
        ]);

        $rule = Rule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        $methods = $validated['methods'] ?? [];

        $rule->fill([
            'name' => $validated['name'],
            'operator' => $validated['operator'],
            'comparison_value' => $validated['value'],
            'for_duration' => $validated['for_duration'],
            'email_enabled' => in_array('email', $methods, true),
            'telegram_enabled' => in_array('telegram', $methods, true),
            'discord_enabled' => in_array('discord', $methods, true),
            'template_telegram' => $request->input('template_telegram'),
            'template_email' => $request->input('template_email'),
            'template_discord' => $request->input('template_discord'),
            'recipient_email' => $validated['recipient_email'] ?? null,
        ]);

        $rule->save();

        // Sincroniza dispositivos seleccionados
        $rule->dispositivos()->sync($validated['devices']);

        Log::info("Regla ID {$id} actualizada correctamente");

        return redirect()->back()->with('success', 'Regla actualizada correctamente.');
    }

    public function toggle($id)
    {
        $rule = Rule::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $rule->update(['is_active' => !$rule->is_active]);

        $msg = $rule->is_active ? 'Regla activada.' : 'Regla desactivada.';
        Log::info("Regla ID {$id} " . ($rule->is_active ? 'activada' : 'desactivada'));

        return back()->with('success', $msg);
    }

    public function destroy(Request $request, $id)
    {
        Log::debug("Iniciando eliminación de regla ID {$id}");

        $rule = Rule::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $rule->delete();

        Log::info("Regla ID {$id} eliminada correctamente");

        return redirect()->back()->with('success', 'Regla eliminada correctamente.');
    }
}
