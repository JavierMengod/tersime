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

        $data = $request->validated();

        $programacion = ProgramacionInformes::create(
            array_merge($this->attributesFrom($data), ['user_id' => auth()->id()])
        );

        $programacion->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes.programados')
            ->with('success', 'Programación creada correctamente.');
    }

    public function update(ProgramacionInformeRequest $request, ProgramacionInformes $programacion)
    {
        abort_unless((int) $programacion->user_id === (int) auth()->id(), 403);

        $data = $request->validated();

        $programacion->fill($this->attributesFrom($data))->save();
        $programacion->dispositivos()->sync($data['dispositivos']);

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

    private function attributesFrom(array $data): array
    {
        return [
            'nombre'         => $data['nombre'],
            'tipo_periodo'   => $data['tipo_periodo'],
            'valor_periodo'  => $data['valor_periodo'],
            'telegram'       => $data['telegram'] ?? false,
            'discord'        => $data['discord'] ?? false,
            'correo'         => $data['correo'] ?? false,
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo'         => $data['activo'] ?? false,
        ];
    }
}
