<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Informe;
use App\Traits\ResolvesInformePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    use ResolvesInformePath;

    public function index(Request $request)
    {
        $informes = $request->user()
            ->informes()
            ->with('dispositivos')
            ->latest('generated_at')
            ->paginate((int) $request->input('per_page', 20));

        $informes->getCollection()->transform(fn($informe) => (new ReportResource($informe))->toArray($request));

        return response()->json($informes);
    }

    public function download(Request $request, Informe $informe)
    {
        abort_unless((int) $informe->user_id === (int) $request->user()->id, 403);

        $rutaAbsoluta = $this->resolveInformePath($informe->pdf_path);

        if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        return response()->download(
            $rutaAbsoluta,
            $informe->nombre_archivo ?: basename($rutaAbsoluta),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function destroy(Request $request, Informe $informe)
    {
        abort_unless((int) $informe->user_id === (int) $request->user()->id, 403);

        $this->eliminarPdfInforme($informe->pdf_path);
        $informe->delete();

        return response()->json(['message' => 'Informe eliminado.']);
    }

    private function eliminarPdfInforme(?string $rutaPdf): void
    {
        if (empty($rutaPdf)) {
            return;
        }

        $relativa = ltrim(preg_replace('#^public/#', '', ltrim($rutaPdf, '/')), '/');
        if (Storage::disk('public')->exists($relativa)) {
            Storage::disk('public')->delete($relativa);
            return;
        }

        $absoluta = $this->resolveInformePath($rutaPdf);
        if ($absoluta && is_file($absoluta)) {
            unlink($absoluta);
        }
    }
}
