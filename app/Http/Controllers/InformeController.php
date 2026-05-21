<?php

namespace App\Http\Controllers;

use App\Jobs\GenerarInformeJob;
use App\Models\Informe;
use App\Traits\ResolvesInformePath;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InformeController extends Controller
{
    use ResolvesInformePath;

    public function programados()
    {
        $usuario  = auth()->user();
        $informes = $usuario->programacionInformes()
            ->with('dispositivos')
            ->paginate(15);

        $tg   = $usuario->credencialTelegram;
        $smtp = $usuario->credencialSmtp;
        $dc   = $usuario->credencialDiscord;

        $canalesSinConfig = [];
        if (!$tg || empty($tg->chat_id)) {
            $canalesSinConfig[] = 'telegram';
        }
        if (!$smtp || empty($smtp->host)) {
            $canalesSinConfig[] = 'correo';
        }
        if (!$dc || empty($dc->webhook_url)) {
            $canalesSinConfig[] = 'discord';
        }

        return view('informes.programados', compact('informes', 'canalesSinConfig'));
    }

    public function registro()
    {
        $registros = auth()->user()
            ->informes()
            ->with('dispositivos')
            ->orderByRaw("CASE WHEN estado IN ('pending','processing') THEN 0 ELSE 1 END ASC")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('informes.registro', compact('registros'));
    }

    public function demanda()
    {
        $dispositivos = auth()->user()->dispositivos()->get();
        return view('informes.demanda', compact('dispositivos'));
    }

    public function generarInformeDemanda(Request $request)
    {
        $validado = $request->validate([
            'fromDate'       => 'required|date_format:Y-m-d',
            'toDate'         => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'email'          => 'nullable|email',
            'dispositivos'   => 'required|string',
            'notificaciones' => 'nullable|string',
        ]);

        if (Carbon::parse($validado['fromDate'])->diffInDays(Carbon::parse($validado['toDate'])) > 366) {
            return response()->json(['error' => 'El período máximo permitido es 366 días.'], 422);
        }

        $usuario = auth()->user();

        $pendientes = $usuario->informes()->whereIn('estado', ['pending', 'processing'])->count();
        if ($pendientes >= 3) {
            return response()->json(['error' => 'Tienes informes en proceso. Espera a que finalicen antes de solicitar uno nuevo.'], 429);
        }

        $decodificado = json_decode($validado['dispositivos'], true);
        if (!is_array($decodificado) || empty($decodificado)) {
            return response()->json(['error' => 'Debes seleccionar al menos un dispositivo.'], 422);
        }
        $idsDispositivos = array_column($decodificado, 'id');

        $notificaciones = [];
        if (!empty($validado['notificaciones'])) {
            $tmp = json_decode($validado['notificaciones'], true);
            if (is_array($tmp)) {
                $notificaciones = $tmp;
            }
        }

        $fechaDesde = Carbon::parse($validado['fromDate'])->format('Y-m-d');
        $fechaHasta = Carbon::parse($validado['toDate'])->format('Y-m-d');
        $correoDestino = $validado['email'] ?? null;

        $idsDispositivos = $usuario->dispositivos()
            ->whereIn('dispositivos.id', $idsDispositivos)
            ->pluck('dispositivos.id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        if (empty($idsDispositivos)) {
            return response()->json(['error' => 'Los dispositivos seleccionados no te pertenecen.'], 422);
        }

        $telegram = in_array('telegram', $notificaciones);
        $correo   = in_array('correo', $notificaciones);
        $discord  = in_array('discord', $notificaciones);

        if ($correo && empty($correoDestino)) {
            return response()->json(['error' => 'Se requiere un correo destino cuando se activa la notificación por email.'], 422);
        }

        $informe = null;
        try {
            DB::transaction(function () use (
                $usuario, $fechaDesde, $fechaHasta, $correoDestino, $notificaciones,
                $idsDispositivos, $telegram, $correo, $discord, &$informe
            ) {
                $informe = Informe::create([
                    'user_id'        => $usuario->id,
                    'tipo'           => 'Demanda',
                    'periodo_from'   => $fechaDesde,
                    'periodo_to'     => $fechaHasta,
                    'telegram'       => $telegram,
                    'discord'        => $discord,
                    'correo'         => $correo,
                    'correo_destino' => $correoDestino,
                    'estado'         => 'pending',
                ]);

                if (!empty($idsDispositivos)) {
                    $informe->dispositivos()->sync($idsDispositivos);
                }

                GenerarInformeJob::dispatch(
                    $informe->id,
                    $usuario->id,
                    $idsDispositivos,
                    $fechaDesde,
                    $fechaHasta,
                    $telegram,
                    $correo,
                    $discord,
                    $correoDestino,
                );
            });
        } catch (\Throwable $e) {
            Log::error('[Informe] Error al despachar job', ['error' => $e->getMessage(), 'user_id' => $usuario->id]);
            return response()->json(['error' => 'No se pudo iniciar la generación del informe.'], 500);
        }

        Log::info('[Informe] Job despachado', ['informe_id' => $informe->id, 'user_id' => $usuario->id]);

        return response()->json([
            'queued'     => true,
            'informe_id' => $informe->id,
            'status_url' => route('informes.status', $informe->id, false),
        ]);
    }

    public function status(Informe $informe)
    {
        if ((int) $informe->user_id !== (int) auth()->id()) {
            abort(404);
        }

        $datos = ['status' => $informe->estado];

        if ($informe->estaCompletado()) {
            $datos['download_url'] = route('informes.demanda.download', ['nombreArchivo' => $informe->nombre_archivo], false);
        }

        if ($informe->estaFallido()) {
            $datos['error'] = $informe->mensaje_error;
        }

        return response()->json($datos);
    }

    public function download(Informe $informe)
    {
        $this->autorizarAcceso($informe);
        $rutaAbsoluta = $this->resolverRutaInforme($informe->pdf_path);

        if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
            return back()->with('error', 'No se encontró el archivo en el servidor.');
        }

        return response()->download($rutaAbsoluta, $informe->nombre_archivo ?: basename($rutaAbsoluta), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(Informe $informe)
    {
        $this->autorizarAcceso($informe);

        if (!empty($informe->pdf_path)) {
            $rutaAbsoluta = $this->resolverRutaInforme($informe->pdf_path);
            if ($rutaAbsoluta && is_file($rutaAbsoluta)) {
                unlink($rutaAbsoluta);
            }
        }

        $informe->delete();
        return back()->with('success', 'Registro eliminado correctamente.');
    }

    public function descargarBajoDemanda(string $nombreArchivo)
    {
        $informe = \App\Models\Informe::where('nombre_archivo', $nombreArchivo)->firstOrFail();
        return redirect()->route('informes.download', $informe->id);
    }

    private function autorizarAcceso(Informe $informe): void
    {
        if ((int) $informe->user_id !== (int) auth()->id()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }
}
