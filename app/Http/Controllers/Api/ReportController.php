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
        $reports = $request->user()
            ->informes()
            ->with('dispositivos')
            ->latest('generated_at')
            ->paginate((int) $request->input('per_page', 20));

        $reports->getCollection()->transform(fn($r) => (new ReportResource($r))->toArray($request));

        return response()->json($reports);
    }

    public function download(Request $request, Informe $informe)
    {
        abort_unless((int) $informe->user_id === (int) $request->user()->id, 403);

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
        abort_unless((int) $informe->user_id === (int) $request->user()->id, 403);

        $this->deleteInformePdf($informe->pdf_path);
        $informe->delete();

        return response()->json(['message' => 'Informe eliminado.']);
    }

    private function deleteInformePdf(?string $pdfPath): void
    {
        if (empty($pdfPath)) {
            return;
        }

        $relative = ltrim(preg_replace('#^public/#', '', ltrim($pdfPath, '/')), '/');
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
            return;
        }

        $abs = $this->resolveInformePath($pdfPath);
        if ($abs && is_file($abs)) {
            unlink($abs);
        }
    }
}
