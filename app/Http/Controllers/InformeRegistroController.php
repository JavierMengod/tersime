<?php

namespace App\Http\Controllers;

use App\Models\InformeRegistro;
use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class InformeRegistroController extends Controller
{
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

        [$absolutePath, $downloadName] = $this->resolvePdfAbsolutePath($registro->pdf_path, $registro->nombre_archivo);

        if (!is_file($absolutePath)) {
            return back()->with('error', "No se encontró el archivo en el servidor: {$absolutePath}");
        }

        return response()->download($absolutePath, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(InformeRegistro $registro)
    {
        $this->authorizeAccess($registro);

        // eliminar archivo físico si existe (opcional)
        [$absolutePath] = $this->resolvePdfAbsolutePath($registro->pdf_path, $registro->nombre_archivo);
        if (is_file($absolutePath)) {
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

    /**
     * Resuelve una ruta absoluta válida al PDF.
     * Acepta casos:
     *  - Ruta absoluta (/var/www/... o C:\...).
     *  - Rutas con prefijos: storage/app/public/..., public/..., storage/...
     *  - Ruta relativa dentro del disco public (p.ej. informes/archivo.pdf)
     */
    private function resolvePdfAbsolutePath(string $pdfPath, string $downloadName): array
    {
        // Si ya es ruta absoluta (Linux/Windows), úsala tal cual
        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $pdfPath) === 1) {
            return [$pdfPath, $downloadName];
        }

        // Normalizar: quitar posibles prefijos comunes
        $relative = ltrim($pdfPath, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative);
        $relative = preg_replace('#^public/#', '', $relative);
        $relative = preg_replace('#^storage/#', '', $relative);

        // Ruta absoluta real dentro de storage/app/public
        $absolute = storage_path('app/public/' . $relative);

        return [$absolute, $downloadName];
    }
}
