<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgramacionInformeRequest;
use App\Models\ProgramacionInformes;

class ProgramacionInformesController extends Controller
{
    public function store(ProgramacionInformeRequest $request)
    {
        if (ProgramacionInformes::where('user_id', auth()->id())->count() >= 10) {
            return back()->withErrors(['nombre' => 'Has alcanzado el límite de 10 programaciones.']);
        }

        $datos = $request->validated();

        $programacion = ProgramacionInformes::create(
            array_merge($this->atributosDesde($datos), ['user_id' => auth()->id()])
        );

        $programacion->dispositivos()->sync($datos['dispositivos']);

        return redirect()->route('informes.programados')
            ->with('success', 'Programación creada correctamente.');
    }

    public function update(ProgramacionInformeRequest $request, ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) auth()->id(), 403);

        $datos = $request->validated();

        $programacion->fill($this->atributosDesde($datos))->save();
        $programacion->dispositivos()->sync($datos['dispositivos']);

        return redirect()->route('informes.programados')
            ->with('success', 'Programación actualizada correctamente.');
    }

    public function destroy(ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) auth()->id(), 403);

        $programacion->delete();

        return redirect()->route('informes.programados')
            ->with('success', 'Programación eliminada correctamente.');
    }

    public function toggle(ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) auth()->id(), 403);

        $programacion->update(['activo' => !$programacion->activo]);

        return back();
    }

    private function atributosDesde(array $datos): array
    {
        return [
            'nombre'         => $datos['nombre'],
            'tipo_periodo'   => $datos['tipo_periodo'],
            'valor_periodo'  => $datos['valor_periodo'],
            'hora_inicio'    => $datos['hora_inicio'] ?? null,
            'telegram'       => $datos['telegram'] ?? false,
            'discord'        => $datos['discord'] ?? false,
            'correo'         => $datos['correo'] ?? false,
            'correo_destino' => $datos['correo_destino'] ?? null,
            'activo'         => $datos['activo'] ?? false,
        ];
    }
}
