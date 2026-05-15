<?php

namespace App\Console\Commands;

use App\Http\Controllers\NotificationMethodController;
use App\Models\ProgramacionInformes;
use App\Notifications\NotificacionInforme;
use App\Services\InformeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerarInformesProgramados extends Command
{
    protected $signature   = 'informes:programados';
    protected $description = 'Genera los informes programados que están vencidos';

    public function handle(InformeService $service, NotificationMethodController $notifier)
    {
        $ahora = Carbon::now();

        // Filtrar en BD los que están vencidos para no traer toda la tabla
        $programaciones = ProgramacionInformes::with(['user', 'dispositivos'])
            ->where('activo', true)
            ->where(function ($q) use ($ahora) {
                $q->whereNull('last_run_at')
                  ->orWhereRaw('DATETIME(last_run_at, "+"||periodicidad||" hours") <= ?', [$ahora->toDateTimeString()]);
            })
            ->get();

        if ($programaciones->isEmpty()) {
            $this->info('No hay informes programados vencidos.');
            return;
        }

        $this->info("Procesando {$programaciones->count()} programación(es)...");

        foreach ($programaciones as $programacion) {
            $user = $programacion->user;

            if (!$user) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin usuario.");
                continue;
            }

            $dispositivos = $programacion->dispositivos;

            if ($dispositivos->isEmpty()) {
                Log::warning("[InformesProgramados] Programación {$programacion->id} sin dispositivos.");
                continue;
            }

            $toDate   = $ahora->toDateString();
            $fromDate = $this->calcularFromDate($ahora, $programacion);

            try {
                $result = $service->generarPdf(
                    $user,
                    $dispositivos,
                    $fromDate,
                    $toDate,
                    'Programado',
                    $programacion->correo_destino,
                    $programacion->telegram,
                    $programacion->correo,
                    $programacion->discord,
                    $programacion->correo_destino
                );

                $absolutePath = $result['absolutePath'];
                $texto        = "📊 Informe programado '{$programacion->nombre}' — período {$fromDate} al {$toDate}.";

                if ($programacion->telegram) {
                    try {
                        $notifier->sendTelegramWithAttachment($texto, $user, $absolutePath);
                    } catch (\Throwable $e) {
                        Log::error("[InformesProgramados] Error Telegram: " . $e->getMessage());
                    }
                }

                if ($programacion->correo && $programacion->correo_destino) {
                    try {
                        $notifier->sendEmailWithAttachment($texto, $user, $programacion->correo_destino, $absolutePath);
                    } catch (\Throwable $e) {
                        Log::error("[InformesProgramados] Error Email: " . $e->getMessage());
                    }
                }

                if ($programacion->discord) {
                    try {
                        $notifier->sendDiscordWithFile($texto, $absolutePath, $user);
                    } catch (\Throwable $e) {
                        Log::error("[InformesProgramados] Error Discord: " . $e->getMessage());
                    }
                }

                try {
                    $user->notify(new NotificacionInforme($result['filename'], $fromDate, $toDate));
                } catch (\Throwable $e) {
                    Log::warning("[InformesProgramados] Notificación DB fallida: " . $e->getMessage());
                }

                $programacion->update(['last_run_at' => $ahora]);

                $this->info("  ✔ Programación '{$programacion->nombre}' generada ({$result['filename']}).");
                Log::info("[InformesProgramados] Generado para programación {$programacion->id}", ['filename' => $result['filename']]);
            } catch (\Throwable $e) {
                $this->error("  ✘ Error en programación '{$programacion->nombre}': " . $e->getMessage());
                Log::error("[InformesProgramados] Error en programación {$programacion->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('Proceso completado.');
    }

    private function calcularFromDate(Carbon $ahora, ProgramacionInformes $p): string
    {
        $valor = (int) ($p->valor_periodo ?? 1);
        $tipo  = $p->tipo_periodo ?? 'horas';

        switch ($tipo) {
            case 'meses': return $ahora->copy()->subMonths($valor)->toDateString();
            case 'dias':  return $ahora->copy()->subDays($valor)->toDateString();
            default:      return $ahora->copy()->subHours($valor)->toDateString();
        }
    }
}
