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

    public $timeout = 7200;
    public $tries   = 2;
    public $backoff = [30, 120];

    public int    $idInforme;
    public int    $idUsuario;
    public array  $idsDispositivos;
    public string $fechaDesde;
    public string $fechaHasta;
    public bool   $telegram;
    public bool   $correo;
    public bool   $discord;
    public ?string $correoDestino;

    public function __construct(
        int    $idInforme,
        int    $idUsuario,
        array  $idsDispositivos,
        string $fechaDesde,
        string $fechaHasta,
        bool   $telegram,
        bool   $correo,
        bool   $discord,
        ?string $correoDestino
    ) {
        $this->idInforme       = $idInforme;
        $this->idUsuario       = $idUsuario;
        $this->idsDispositivos = $idsDispositivos;
        $this->fechaDesde      = $fechaDesde;
        $this->fechaHasta      = $fechaHasta;
        $this->telegram        = $telegram;
        $this->correo          = $correo;
        $this->discord         = $discord;
        $this->correoDestino   = $correoDestino;
        $this->afterCommit();
    }

    public function handle(InformeService $servicio, NotificationService $notificador): void
    {
        $informe = Informe::findOrFail($this->idInforme);

        try {
            $informe->update(['status' => 'processing']);

            $usuario      = User::findOrFail($this->idUsuario);
            $dispositivos = Dispositivo::whereIn('id', $this->idsDispositivos)->get();

            $resultado = $servicio->generarPdfParaInformeExistente(
                $informe,
                $usuario,
                $dispositivos,
                $this->fechaDesde,
                $this->fechaHasta,
                $this->telegram,
                $this->correo,
                $this->discord,
                $this->correoDestino,
            );

            $rutaAbsoluta = $resultado['absolutePath'];
            $desde  = Carbon::parse($this->fechaDesde)->format('d/m/Y');
            $hasta  = Carbon::parse($this->fechaHasta)->format('d/m/Y');
            $texto  = "📊 Tu informe del período {$desde} al {$hasta} está listo.";

            if ($this->telegram) {
                try {
                    $notificador->sendTelegramWithAttachment($texto, $usuario, $rutaAbsoluta);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Telegram', ['error' => $e->getMessage()]);
                }
            }

            if ($this->correo && $this->correoDestino) {
                try {
                    $notificador->sendEmailWithAttachment($texto, $usuario, $this->correoDestino, $rutaAbsoluta);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Email', ['error' => $e->getMessage()]);
                }
            }

            if ($this->discord) {
                try {
                    $notificador->sendDiscordWithFile($texto, $rutaAbsoluta, $usuario);
                } catch (\Throwable $e) {
                    Log::warning('[GenerarInformeJob] Error Discord', ['error' => $e->getMessage()]);
                }
            }

            try {
                $usuario->notify(new NotificacionInforme($informe->id, $this->fechaDesde, $this->fechaHasta));
            } catch (\Throwable $e) {
                Log::warning('[GenerarInformeJob] Notificación DB fallida', ['error' => $e->getMessage()]);
            }

            $informe->update(['status' => 'completed']);
            Log::info('[GenerarInformeJob] Completado', ['informe_id' => $this->idInforme]);
        } catch (\Throwable $e) {
            Log::error('[GenerarInformeJob] Falló', [
                'informe_id' => $this->idInforme,
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
        $informe = Informe::find($this->idInforme);
        if ($informe && $informe->status !== 'failed') {
            $informe->update([
                'status'        => 'failed',
                'error_message' => 'El proceso fue interrumpido: ' . $e->getMessage(),
            ]);
        }
        Log::error('[GenerarInformeJob] Job eliminado por el worker', [
            'informe_id' => $this->idInforme,
            'error'      => $e->getMessage(),
        ]);
    }
}
