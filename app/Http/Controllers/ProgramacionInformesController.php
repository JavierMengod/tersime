<?php

namespace App\Http\Controllers;

use App\Models\ProgramacionInformes;
use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramacionInformesController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $programaciones = ProgramacionInformes::with('dispositivos')
            ->where('user_id', $user->id)
            ->get();

        $devices = Dispositivo::all();

        return view('informes.programados', compact('programaciones', 'devices'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo_periodo' => 'required|in:horas,dias,meses',
            'valor_periodo' => 'required|integer|min:1',
            'dispositivos' => 'required|array|min:1',
            'dispositivos.*' => 'integer|exists:dispositivos,id',
            'correo_destino' => 'nullable|email',
            'telegram' => 'sometimes|boolean',
            'discord' => 'sometimes|boolean',
            'correo' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        // Calcular periodicidad (en horas)
        switch ($data['tipo_periodo']) {
            case 'dias':
                $periodicidad = $data['valor_periodo'] * 24;
                break;
            case 'meses':
                $periodicidad = $data['valor_periodo'] * 24 * 30;
                break;
            default:
                $periodicidad = $data['valor_periodo'];
                break;
        }

        $programacion = ProgramacionInformes::create([
            'user_id' => Auth::id(),
            'nombre' => $data['nombre'],
            'periodicidad' => $periodicidad, // 👈 aquí el cambio clave
            'telegram' => $request->has('telegram'),
            'discord' => $request->has('discord'),
            'correo' => $request->has('correo'),
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo' => $request->has('activo'),
        ]);

        $programacion->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes-programados')
            ->with('success', 'Programación creada correctamente.');
    }

    public function update(Request $request, ProgramacionInformes $programacionInformes)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo_periodo' => 'required|in:horas,dias,meses',
            'valor_periodo' => 'required|integer|min:1',
            'dispositivos' => 'required|array|min:1',
            'dispositivos.*' => 'integer|exists:dispositivos,id',
            'correo_destino' => 'nullable|email',
            'telegram' => 'sometimes|boolean',
            'discord' => 'sometimes|boolean',
            'correo' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        switch ($data['tipo_periodo']) {
            case 'dias':
                $periodicidad = $data['valor_periodo'] * 24;
                break;
            case 'meses':
                $periodicidad = $data['valor_periodo'] * 24 * 30;
                break;
            default:
                $periodicidad = $data['valor_periodo'];
                break;
        }

        $programacionInformes->update([
            'nombre' => $data['nombre'],
            'periodicidad' => $periodicidad, // 👈 y aquí también
            'telegram' => $request->has('telegram'),
            'discord' => $request->has('discord'),
            'correo' => $request->has('correo'),
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo' => $request->has('activo'),
        ]);

        $programacionInformes->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes-programados')
            ->with('success', 'Programación actualizada correctamente.');
    }

    public function destroy(ProgramacionInformes $programacionInformes)
    {
        $programacionInformes->delete();

        return redirect()->route('informes-programados')
            ->with('success', 'Programación eliminada correctamente.');
    }
}
