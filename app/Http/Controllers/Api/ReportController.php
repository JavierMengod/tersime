<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Informe;
use App\Traits\ResolvesInformePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    use ResolvesInformePath;
    public function index(Request $request)
    {
        $reports = $request->user()
            ->informes()
            ->with('dispositivos')
            ->latest('generated_at')
            ->paginate((int) $request->input('per_page', 20));

        $reports->getCollection()->transform(function ($r) {
            return $this->format($r);
        });

        return response()->json($reports);
    }

    public function download(Request $request, Informe $informe)
    {
        if ((int) $informe->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        $absolutePath = $this->resolveInformePath($informe->pdf_path);

        if (!$absolutePath || !is_file($absolutePath)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        return response()->download(
            $absolutePath,
            $informe->nombre_archivo ?: basename($absolutePath),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function destroy(Request $request, Informe $informe)
    {
        if ((int) $informe->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        if (!empty($informe->pdf_path)) {
            $relative = ltrim(preg_replace('#^public/#', '', ltrim($informe->pdf_path, '/')), '/');
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            } else {
                $abs = $this->resolveInformePath($informe->pdf_path);
                if ($abs && is_file($abs)) {
                    @unlink($abs);
                }
            }
        }

        $informe->delete();

        return response()->json(['message' => 'Informe eliminado.']);
    }

    private function format(Informe $r): array
    {
        return [
            'id'             => $r->id,
            'nombre_archivo' => $r->nombre_archivo,
            'tipo'           => $r->tipo ?? null,
            'from'           => $r->periodo_from,
            'to'             => $r->periodo_to,
            'generated_at'   => $r->generated_at,
            'size_bytes'     => $r->size_bytes ?? null,
            'dispositivos'   => $r->dispositivos->map(function ($d) {
                return ['id' => $d->id, 'influx_tag' => $d->influx_tag];
            }),
        ];
    }

}
