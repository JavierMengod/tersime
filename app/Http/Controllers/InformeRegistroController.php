<?php

namespace App\Http\Controllers;

use App\Models\InformeRegistro;
use App\Models\Dispositivo;
use App\Traits\ResolvesInformePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InformeRegistroController extends Controller
{
    use ResolvesInformePath;
    public function index()
    {
        $registros = InformeRegistro::where('user_id', auth()->id())
            ->latest('generated_at')
            ->get();

        // Traemos los dispositivos del usuario autenticado en un mapa [id => nombre]
        $mapaDispositivos = auth()->user()
            ->dispositivos()
            ->select('dispositivos.id', 'dispositivos.nombre') // <- especificamos la tabla
            ->pluck('nombre', 'id')
            ->toArray();

        // Normalizamos dispositivos para cada registro
        $dispositivos = $registros->mapWithKeys(function ($r) use ($mapaDispositivos) {
            $raw = $r->dispositivos;

            // Aseguramos array válido
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [$raw];
            }
            if (!is_array($raw)) {
                $raw = [];
            }

            // Reemplazar IDs por nombres
            $nombres = collect($raw)
                ->map(function ($d) use ($mapaDispositivos) {
                    // Si $d es array y tiene 'id', usamos eso
                    $id = is_array($d) && isset($d['id']) ? $d['id'] : $d;
                    return $mapaDispositivos[$id] ?? null;
                })
                ->filter()
                ->values()
                ->all();

            return [$r->id => $nombres];
        })->toArray();

        Log::info('Dispositivos normalizados con nombres', ['dispositivos' => $dispositivos]);

        return view('informes.registro', [
            'registros' => $registros,
            'dispositivos' => $dispositivos,
        ]);
    }

    public function download(InformeRegistro $registro)
    {
        $this->authorizeAccess($registro);

        $absolutePath = $this->resolveInformePath($registro->pdf_path);

        if (!$absolutePath || !is_file($absolutePath)) {
            return back()->with('error', 'No se encontró el archivo en el servidor.');
        }

        return response()->download($absolutePath, $registro->nombre_archivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(InformeRegistro $registro)
    {
        $this->authorizeAccess($registro);

        $absolutePath = $this->resolveInformePath($registro->pdf_path);
        if ($absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $registro->delete();

        return back()->with('success', 'Registro eliminado correctamente.');
    }

    private function authorizeAccess(InformeRegistro $registro)
    {
        if ($registro->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }

}
