<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\Informe;
use App\Notifications\NotificacionInforme;
use App\Services\InformeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InformeController extends Controller
{
    protected InformeService $informeService;
    protected NotificationMethodController $notifier;

    public function __construct(InformeService $informeService, NotificationMethodController $notifier)
    {
        $this->informeService = $informeService;
        $this->notifier       = $notifier;
    }

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
            ->latest('generated_at')
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
            'dispositivos'   => 'nullable|string',
            'notificaciones' => 'nullable|string',
        ]);

        $user = auth()->user();
        Log::info('Inicio generación informe bajo demanda', ['user_id' => $user->id]);

        try {
            $dispositivosIds = [];
            if (!empty($validated['dispositivos'])) {
                $decoded = json_decode($validated['dispositivos'], true);
                if (is_array($decoded)) {
                    $dispositivosIds = array_column($decoded, 'id');
                }
            }

            $dispositivos = $dispositivosIds
                ? Dispositivo::whereIn('id', $dispositivosIds)->get()
                : collect();

            $notificaciones = [];
            if (!empty($validated['notificaciones'])) {
                $tmp = json_decode($validated['notificaciones'], true);
                if (is_array($tmp)) {
                    $notificaciones = $tmp;
                }
            }

            $fromDate = Carbon::parse($validated['fromDate'])->format('Y-m-d');
            $toDate   = Carbon::parse($validated['toDate'])->format('Y-m-d');

            $result = $this->informeService->generarPdf(
                $user,
                $dispositivos,
                $fromDate,
                $toDate,
                'Demanda',
                $validated['email'] ?? null,
                in_array('telegram', $notificaciones),
                in_array('correo', $notificaciones),
                in_array('discord', $notificaciones),
                $validated['email'] ?? null
            );

            $absolutePath = $result['absolutePath'];
            $texto        = "📊 Tu informe del período {$fromDate} al {$toDate} está listo.";

            if (in_array('telegram', $notificaciones)) {
                try {
                    $this->notifier->sendTelegramWithAttachment($texto, $user, $absolutePath);
                } catch (\Throwable $e) {
                    Log::warning('[Informe] Error enviando Telegram', ['error' => $e->getMessage()]);
                }
            }

            if (in_array('correo', $notificaciones) && !empty($validated['email'])) {
                try {
                    $this->notifier->sendEmailWithAttachment($texto, $user, $validated['email'], $absolutePath);
                } catch (\Throwable $e) {
                    Log::warning('[Informe] Error enviando Email', ['error' => $e->getMessage()]);
                }
            }

            if (in_array('discord', $notificaciones)) {
                try {
                    $this->notifier->sendDiscordWithFile($texto, $absolutePath, $user);
                } catch (\Throwable $e) {
                    Log::warning('[Informe] Error enviando Discord', ['error' => $e->getMessage()]);
                }
            }

            try {
                $user->notify(new NotificacionInforme($result['filename'], $fromDate, $toDate));
            } catch (\Throwable $e) {
                Log::warning('[Informe] Notificación DB fallida', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success'      => true,
                'download_url' => $result['downloadUrl'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error generando informe bajo demanda: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el informe',
            ], 500);
        }
    }

    public function download(Informe $informe)
    {
        $this->authorizeAccess($informe);
        [$absolutePath, $downloadName] = $this->resolvePdfAbsolutePath($informe->pdf_path, $informe->nombre_archivo);

        if (empty($absolutePath) || !is_file($absolutePath)) {
            return back()->with('error', 'No se encontró el archivo en el servidor.');
        }

        return response()->download($absolutePath, $downloadName ?: basename($absolutePath), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function destroy(Informe $informe)
    {
        $this->authorizeAccess($informe);

        if (!empty($informe->pdf_path)) {
            $relative = ltrim(preg_replace('#^public/#', '', ltrim($informe->pdf_path, '/')), '/');
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            } else {
                [$absolutePath] = $this->resolvePdfAbsolutePath($informe->pdf_path, $informe->nombre_archivo);
                if ($absolutePath && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        $informe->delete();
        return back()->with('success', 'Registro eliminado correctamente.');
    }

    public function descargarBajoDemanda(string $filename)
    {
        $path = storage_path('app/public/informes/' . $filename);
        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    private function authorizeAccess(Informe $informe): void
    {
        if ($informe->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }

    private function resolvePdfAbsolutePath(?string $pdfPath, ?string $downloadName): array
    {
        if (empty($pdfPath)) {
            return [null, $downloadName];
        }

        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $pdfPath) === 1) {
            return [$pdfPath, $downloadName];
        }

        $relative = ltrim($pdfPath, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative);
        $relative = preg_replace('#^public/#', '', $relative);
        $relative = preg_replace('#^storage/#', '', $relative);

        return [storage_path('app/public/' . $relative), $downloadName];
    }
}
