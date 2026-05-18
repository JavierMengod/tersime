<?php

namespace App\Http\Controllers;

use App\Models\ProgramacionInformes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProgramacionInformesController extends Controller
{
    public function store(Request $request)
    {
        if (ProgramacionInformes::where('user_id', Auth::id())->count() >= 10) {
            return back()->withErrors(['nombre' => 'Has alcanzado el límite de 10 programaciones.']);
        }

        $data = $this->validar($request);

        $programacion = ProgramacionInformes::create([
            'user_id'        => Auth::id(),
            'nombre'         => $data['nombre'],
            'tipo_periodo'   => $data['tipo_periodo'],
            'valor_periodo'  => $data['valor_periodo'],
            'telegram'       => $data['telegram'] ?? false,
            'discord'        => $data['discord'] ?? false,
            'correo'         => $data['correo'] ?? false,
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo'         => $data['activo'] ?? false,
        ]);

        $programacion->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes.programados')
            ->with('success', 'Programación creada correctamente.');
    }

    public function update(Request $request, ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) Auth::id(), 403);

        $data = $this->validar($request);

        $programacion->update([
            'nombre'         => $data['nombre'],
            'tipo_periodo'   => $data['tipo_periodo'],
            'valor_periodo'  => $data['valor_periodo'],
            'telegram'       => $data['telegram'] ?? false,
            'discord'        => $data['discord'] ?? false,
            'correo'         => $data['correo'] ?? false,
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo'         => $data['activo'] ?? false,
        ]);

        $programacion->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes.programados')
            ->with('success', 'Programación actualizada correctamente.');
    }

    public function destroy(ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) Auth::id(), 403);

        $programacion->delete();

        return redirect()->route('informes.programados')
            ->with('success', 'Programación eliminada correctamente.');
    }

    public function toggle(ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) Auth::id(), 403);

        $programacion->update(['activo' => !$programacion->activo]);

        return back();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'         => 'required|string|max:255',
            'tipo_periodo'   => 'required|in:horas,dias,meses',
            'valor_periodo'  => 'required|integer|min:1|max:8760',
            'dispositivos'   => 'required|array|min:1',
            'dispositivos.*' => ['integer', Rule::exists('user_dispositivo', 'dispositivo_id')->where('user_id', Auth::id())],
            'correo'         => 'sometimes|boolean',
            'correo_destino' => 'nullable|email|required_if:correo,1',
            'telegram'       => 'sometimes|boolean',
            'discord'        => 'sometimes|boolean',
            'activo'         => 'sometimes|boolean',
        ]);
    }

}
