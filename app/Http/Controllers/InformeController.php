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
        $user   = auth()->user();
        $informes = $user->programacionInformes()
            ->with('dispositivos')
            ->paginate(15);

        $tg   = $user->telegramCredential;
        $smtp = $user->smtpCredential;
        $dc   = $user->discordCredential;

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
            ->orderByRaw("CASE WHEN status IN ('pending','processing') THEN 0 ELSE 1 END ASC")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('informes.registro', compact('registros'));
    }

    public function demanda()
    {
        $dispositivos = auth()->user()->dispositivos()->get() ?? collect();
        return view('informes.demanda', compact('dispositivos'));
    }

    public function generarInformeDemanda(Request $request)
    {
        $validated = $request->validate([
            'fromDate'       => 'required|date_format:Y-m-d',
            'toDate'         => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'email'          => 'nullable|email',
            'dispositivos'   => 'required|string',
            'notificaciones' => 'nullable|string',
        ]);

        if (Carbon::parse($validated['fromDate'])->diffInDays(Carbon::parse($validated['toDate'])) > 366) {
            return response()->json(['error' => 'El período máximo permitido es 366 días.'], 422);
        }

        $user = auth()->user();

        $pendientes = $user->informes()->whereIn('status', ['pending', 'processing'])->count();
        if ($pendientes >= 3) {
            return response()->json(['error' => 'Tienes informes en proceso. Espera a que finalicen antes de solicitar uno nuevo.'], 429);
        }

        $decoded = json_decode($validated['dispositivos'], true);
        if (!is_array($decoded) || empty($decoded)) {
            return response()->json(['error' => 'Debes seleccionar al menos un dispositivo.'], 422);
        }
        $dispositivosIds = array_column($decoded, 'id');

        $notificaciones = [];
        if (!empty($validated['notificaciones'])) {
            $tmp = json_decode($validated['notificaciones'], true);
            if (is_array($tmp)) {
                $notificaciones = $tmp;
            }
        }

        $fromDate = Carbon::parse($validated['fromDate'])->format('Y-m-d');
        $toDate   = Carbon::parse($validated['toDate'])->format('Y-m-d');
        $email    = $validated['email'] ?? null;

        // Only keep IDs that actually belong to this user
        $dispositivosIds = $user->dispositivos()
            ->whereIn('dispositivos.id', $dispositivosIds)
            ->pluck('dispositivos.id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        if (empty($dispositivosIds)) {
            return response()->json(['error' => 'Los dispositivos seleccionados no te pertenecen.'], 422);
        }

        $telegram = in_array('telegram', $notificaciones);
        $correo   = in_array('correo', $notificaciones);
        $discord  = in_array('discord', $notificaciones);

        if ($correo && empty($email)) {
            return response()->json(['error' => 'Se requiere un correo destino cuando se activa la notificación por email.'], 422);
        }

        $informe = null;
        try {
            DB::transaction(function () use (
                $user, $fromDate, $toDate, $email, $notificaciones,
                $dispositivosIds, $telegram, $correo, $discord, &$informe
            ) {
                $informe = Informe::create([
                    'user_id'        => $user->id,
                    'tipo'           => 'Demanda',
                    'periodo_from'   => $fromDate,
                    'periodo_to'     => $toDate,
                    'telegram'       => $telegram,
                    'discord'        => $discord,
                    'correo'         => $correo,
                    'correo_destino' => $email,
                    'status'         => 'pending',
                ]);

                if (!empty($dispositivosIds)) {
                    $informe->dispositivos()->sync($dispositivosIds);
                }

                GenerarInformeJob::dispatch(
                    $informe->id,
                    $user->id,
                    $dispositivosIds,
                    $fromDate,
                    $toDate,
                    $telegram,
                    $correo,
                    $discord,
                    $email,
                );
            });
        } catch (\Throwable $e) {
            Log::error('[Informe] Error al despachar job', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['error' => 'No se pudo iniciar la generación del informe.'], 500);
        }

        Log::info('[Informe] Job despachado', ['informe_id' => $informe->id, 'user_id' => $user->id]);

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

        $data = ['status' => $informe->status];

        if ($informe->isCompleted()) {
            $data['download_url'] = route('informes.demanda.download', ['filename' => $informe->nombre_archivo], false);
        }

        if ($informe->isFailed()) {
            $data['error'] = $informe->error_message;
        }

        return response()->json($data);
    }

    public function download(Informe $informe)
    {
        $this->authorizeAccess($informe);
        $absolutePath = $this->resolveInformePath($informe->pdf_path);

        if (!$absolutePath || !is_file($absolutePath)) {
            return back()->with('error', 'No se encontró el archivo en el servidor.');
        }

        return response()->download($absolutePath, $informe->nombre_archivo ?: basename($absolutePath), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(Informe $informe)
    {
        $this->authorizeAccess($informe);

        if (!empty($informe->pdf_path)) {
            $absolutePath = $this->resolveInformePath($informe->pdf_path);
            if ($absolutePath && is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }

        $informe->delete();
        return back()->with('success', 'Registro eliminado correctamente.');
    }

    public function descargarBajoDemanda(string $filename)
    {
        $informe = \App\Models\Informe::where('nombre_archivo', $filename)->firstOrFail();
        return redirect()->route('informes.download', $informe->id);
    }

    private function authorizeAccess(Informe $informe): void
    {
        if ((int) $informe->user_id !== (int) auth()->id()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }

}
