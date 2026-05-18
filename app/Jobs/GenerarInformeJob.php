<?php

namespace App\Jobs;

use App\Models\Informe;
use App\Models\User;
use App\Models\Dispositivo;
use App\Notifications\NotificacionInforme;
use App\Services\InformeService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerarInformeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;
    public $tries   = 1;

    public int    $informeId;
    public int    $userId;
    public array  $dispositivosIds;
    public string $fromDate;
    public string $toDate;
    public bool   $telegram;
    public bool   $correo;
    public bool   $discord;
    public ?string $correoDestino;

    public function __construct(
        int    $informeId,
        int    $userId,
        array  $dispositivosIds,
        string $fromDate,
        string $toDate,
        bool   $telegram,
        bool   $correo,
        bool   $discord,
        ?string $correoDestino
    ) {
        $this->informeId       = $informeId;
        $this->userId          = $userId;
        $this->dispositivosIds = $dispositivosIds;
        $this->fromDate        = $fromDate;
        $this->toDate          = $toDate;
        $this->telegram        = $telegram;
        $this->correo          = $correo;
        $this->discord         = $discord;
        $this->correoDestino   = $correoDestino;
        $this->afterCommit();
    }

    public function handle(InformeService $service, NotificationService $notifier): void
    {
        $informe = Informe::findOrFail($this->informeId);

        try {
            $informe->update(['status' => 'processing']);

            $user         = User::findOrFail($this->userId);
            $dispositivos = Dispositivo::whereIn('id', $this->dispositivosIds)->get();

            $result = $service->generarPdfParaInformeExistente(
                $informe,
                $user,
                $dispositivos,
                $this->fromDate,
                $this->toDate,
                $this->telegram,
                $this->correo,
                $this->discord,
                $this->correoDestino,
            );

            $absolutePath = $result['absolutePath'];
            $desde  = Carbon::parse($this->fromDate)->format('d/m/Y');
            $hasta  = Carbon::parse($this->toDate)->format('d/m/Y');
            $texto  = "📊 Tu informe del período {$desde} al {$hasta} está listo.";

            if ($this->telegram) {
                try {
                    $notifier->sendTelegramWithAttachment($texto, $user, $absolutePath);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Telegram', ['error' => $e->getMessage()]);
                }
            }

            if ($this->correo && $this->correoDestino) {
                try {
                    $notifier->sendEmailWithAttachment($texto, $user, $this->correoDestino, $absolutePath);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Email', ['error' => $e->getMessage()]);
                }
            }

            if ($this->discord) {
                try {
                    $notifier->sendDiscordWithFile($texto, $absolutePath, $user);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Discord', ['error' => $e->getMessage()]);
                }
            }

            try {
                $user->notify(new NotificacionInforme($informe->id, $this->fromDate, $this->toDate));
            } catch (\Throwable $e) {
                Log::warning('[GenerarInformeJob] Notificación DB fallida', ['error' => $e->getMessage()]);
            }

            $informe->update(['status' => 'completed']);
            Log::info('[GenerarInformeJob] Completado', ['informe_id' => $this->informeId]);
        } catch (\Throwable $e) {
            Log::error('[GenerarInformeJob] Falló', [
                'informe_id' => $this->informeId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            $informe->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $informe = Informe::find($this->informeId);
        if ($informe && $informe->status !== 'failed') {
            $informe->update([
                'status'        => 'failed',
                'error_message' => 'El proceso fue interrumpido: ' . $e->getMessage(),
            ]);
        }
        Log::error('[GenerarInformeJob] Job eliminado por el worker', [
            'informe_id' => $this->informeId,
            'error'      => $e->getMessage(),
        ]);
    }
}
