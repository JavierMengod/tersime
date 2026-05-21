<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Dispositivo;
use App\Models\Regla;
use App\Models\RegistroAlerta;
use App\Models\User;
use App\Services\InfluxService;
use App\Services\NotificationService;
use App\Notifications\NotificacionAlerta;
use Illuminate\Support\Facades\Log;

class VerificarReglas extends Command
{
    protected $signature   = 'reglas:verificar';
    protected $description = 'Revisar reglas activas y actualizar estados de alerta (ok / pending / firing)';

    public function handle(NotificationService $notificador, InfluxService $influx)
    {
        $inicio = microtime(true);
        $this->info('=== Inicio de revisión de reglas ===');
        Log::info('Inicio de revisión de reglas');

        $reglas = Regla::with(['dispositivos', 'usuario'])
            ->where('activo', true)
            ->get();

        foreach ($reglas as $regla) {
            $usuario       = $regla->usuario;
            $nombreUsuario = $usuario ? $usuario->name : 'Desconocido';

            foreach ($regla->dispositivos as $dispositivo) {
                $etiqueta    = $dispositivo->etiqueta_influx;
                $valorActual = $influx->ultimoValor($etiqueta);

                $condicionCumplida = ($valorActual === null)
                    || $this->evaluarCondicion($valorActual, $regla->operador, $regla->valor_comparacion);

                $estado         = $dispositivo->pivot->alert_state ?? 'ok';
                $pendienteDesde = $dispositivo->pivot->pending_since
                    ? Carbon::parse($dispositivo->pivot->pending_since)
                    : null;

                $etiquetaValor = $valorActual === null ? 'sin datos' : "{$valorActual} kWh";
                $this->line("[{$nombreUsuario}] {$regla->nombre} | {$dispositivo->nombre} = {$etiquetaValor} | estado={$estado} | condición=" . ($condicionCumplida ? 'SÍ' : 'NO'));

                Log::info('Evaluando regla', [
                    'regla_id'          => $regla->id,
                    'dispositivo'       => $dispositivo->nombre,
                    'valor_actual'      => $valorActual,
                    'condicion_cumplida'=> $condicionCumplida,
                    'estado'            => $estado,
                ]);

                switch ($estado) {
                    case 'ok':
                        if ($condicionCumplida) {
                            if ($regla->duracion === 0) {
                                $this->transicionActiva($regla, $dispositivo, $notificador, $usuario, $valorActual);
                            } else {
                                $this->transicionPendiente($regla, $dispositivo);
                                $this->line("  → Condición cumplida, esperando ventana de {$regla->duracion} min.");
                            }
                        }
                        break;

                    case 'pending':
                        if ($condicionCumplida) {
                            $minutos = $pendienteDesde ? $pendienteDesde->diffInMinutes(Carbon::now()) : 0;
                            if ($minutos >= $regla->duracion) {
                                $this->transicionActiva($regla, $dispositivo, $notificador, $usuario, $valorActual);
                            } else {
                                $restantes = $regla->duracion - $minutos;
                                $this->line("  → Pendiente, faltan {$restantes} min para confirmar.");
                            }
                        } else {
                            $this->transicionOk($regla, $dispositivo);
                            $this->line("  → Condición ya no cumplida, reseteo a OK (falsa alarma).");
                        }
                        break;

                    case 'firing':
                        if (!$condicionCumplida) {
                            $this->transicionResuelta($regla, $dispositivo, $notificador, $usuario, $valorActual);
                        } else {
                            $this->line("  → Sigue en FIRING, sin nueva notificación.");
                        }
                        break;
                }
            }
        }

        $transcurrido = round(microtime(true) - $inicio, 2);
        $this->info("=== Fin de revisión de reglas ({$transcurrido}s) ===");
        Log::info('Fin de revisión de reglas', ['transcurrido_s' => $transcurrido, 'reglas_evaluadas' => $reglas->count()]);
    }

    private function transicionPendiente(Regla $regla, Dispositivo $dispositivo): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'pending',
            'pending_since' => Carbon::now()->toDateTimeString(),
        ]);
        Log::info('Estado → pending', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionActiva(Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, ?float $valorActual): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'       => 'firing',
            'pending_since'     => null,
            'ultimo_disparo_en' => Carbon::now()->toDateTimeString(),
        ]);

        $this->info("  → FIRING: enviando alerta.");

        $mensaje = $valorActual === null
            ? "🚨 Sin datos en las últimas 24 h para {$dispositivo->nombre} (regla: {$regla->nombre})"
            : "🚨 Regla '{$regla->nombre}' activada en {$dispositivo->nombre} (valor={$valorActual} kWh)";

        $this->despacharAlerta('firing', $regla, $dispositivo, $notificador, $usuario, $valorActual, $mensaje);
        Log::info('Estado → firing', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionOk(Regla $regla, Dispositivo $dispositivo): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'ok',
            'pending_since' => null,
        ]);
        Log::info('Estado → ok', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionResuelta(Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, ?float $valorActual): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'ok',
            'pending_since' => null,
        ]);

        $this->info("  → RESUELTO: enviando notificación de resolución.");

        $etiquetaValor = $valorActual === null ? 'sin datos' : "{$valorActual} kWh";
        $mensaje       = "✅ Regla '{$regla->nombre}' resuelta en {$dispositivo->nombre} (valor actual={$etiquetaValor})";

        $this->despacharAlerta('resolution', $regla, $dispositivo, $notificador, $usuario, $valorActual, $mensaje);
        Log::info('Estado → ok (resolución)', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function despacharAlerta(string $tipo, Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, ?float $valorActual, string $mensaje): void
    {
        $this->enviarNotificaciones($notificador, $regla, $usuario, $dispositivo, $valorActual, $mensaje);
        $this->registrarLog($regla, $dispositivo, $tipo, $mensaje);

        if ($usuario) {
            try {
                $canales = array_values(array_filter([
                    $regla->correo_activo   ? 'email'    : null,
                    $regla->telegram_activo ? 'telegram' : null,
                    $regla->discord_activo  ? 'discord'  : null,
                ]));
                $usuario->notify(new NotificacionAlerta($tipo, $regla->nombre, $dispositivo->nombre, $mensaje, $canales));
            } catch (\Throwable $e) {
                Log::error("Error guardando notificación DB ({$tipo})", ['error' => $e->getMessage()]);
            }
        }
    }

    private function enviarNotificaciones(
        NotificationService $notificador,
        Regla $regla,
        ?User $usuario,
        Dispositivo $dispositivo,
        ?float $valorActual,
        string $mensajePorDefecto
    ): void {
        if ($regla->correo_activo && $regla->correo_destinatario && $usuario) {
            $mensaje = $regla->plantilla_correo
                ? $this->interpolarPlantilla($regla->plantilla_correo, $regla, $dispositivo, $valorActual)
                : $mensajePorDefecto;
            try {
                $notificador->sendEmail($mensaje, $usuario, $regla->correo_destinatario);
                Log::info('Email enviado', ['regla_id' => $regla->id]);
            } catch (\Throwable $e) {
                $this->error("Error email: " . $e->getMessage());
                Log::error('Error enviando email', ['regla_id' => $regla->id, 'error' => $e->getMessage()]);
            }
        }

        if ($regla->telegram_activo && $usuario) {
            $mensaje = $regla->plantilla_telegram
                ? $this->interpolarPlantilla($regla->plantilla_telegram, $regla, $dispositivo, $valorActual)
                : $mensajePorDefecto;
            try {
                $notificador->sendTelegram($mensaje, $usuario);
                Log::info('Telegram enviado', ['regla_id' => $regla->id]);
            } catch (\Throwable $e) {
                $this->error("Error telegram: " . $e->getMessage());
                Log::error('Error enviando telegram', ['regla_id' => $regla->id, 'error' => $e->getMessage()]);
            }
        }

        if ($regla->discord_activo && $usuario) {
            $mensaje = $regla->plantilla_discord
                ? $this->interpolarPlantilla($regla->plantilla_discord, $regla, $dispositivo, $valorActual)
                : $mensajePorDefecto;
            try {
                $notificador->sendDiscord($mensaje, $usuario);
                Log::info('Discord enviado', ['regla_id' => $regla->id]);
            } catch (\Throwable $e) {
                $this->error("Error discord: " . $e->getMessage());
                Log::error('Error enviando discord', ['regla_id' => $regla->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function interpolarPlantilla(string $plantilla, Regla $regla, Dispositivo $dispositivo, ?float $valorActual): string
    {
        $etiquetaValor = $valorActual === null ? 'sin datos' : "{$valorActual} kWh";
        return str_replace(
            ['{dispositivo}', '{device}', '{regla}',      '{rule}',       '{valor}',         '{value}'],
            [$dispositivo->nombre, $dispositivo->nombre, $regla->nombre, $regla->nombre, $etiquetaValor, $etiquetaValor],
            $plantilla
        );
    }

    private function registrarLog(Regla $regla, Dispositivo $dispositivo, string $tipo, string $mensaje): void
    {
        $canales = [];
        if ($regla->correo_activo)   $canales[] = 'email';
        if ($regla->telegram_activo) $canales[] = 'telegram';
        if ($regla->discord_activo)  $canales[] = 'discord';

        RegistroAlerta::create([
            'user_id'             => $regla->user_id,
            'regla_id'            => $regla->id,
            'nombre_regla'        => $regla->nombre,
            'dispositivo_id'      => $dispositivo->id,
            'nombre_dispositivo'  => $dispositivo->nombre,
            'tipo'                => $tipo,
            'canales'             => $canales ?: null,
            'mensaje'             => $mensaje,
        ]);
    }

    private function evaluarCondicion(float $valor, string $operador, $comparacion): bool
    {
        $vc = (float) $comparacion;

        switch ($operador) {
            case '>':  return $valor >   $vc;
            case '<':  return $valor <   $vc;
            case '>=': return $valor >=  $vc;
            case '<=': return $valor <=  $vc;
            case '==': return abs($valor - $vc) < 1e-9;
            case '!=': return abs($valor - $vc) >= 1e-9;
            default:   return false;
        }
    }
}
