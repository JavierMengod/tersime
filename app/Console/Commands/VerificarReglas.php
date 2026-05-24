<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

    // Minutos mínimos entre resolución y nuevo disparo para el mismo dispositivo+regla.
    private const COOLDOWN_MINUTOS = 60;

    public function handle(NotificationService $notificador, InfluxService $influx)
    {
        // Fix: lock de exclusión mutua — evita solapamiento si una ejecución tarda más de 1 h.
        $lock = Cache::lock('reglas:verificar', 3600);
        if (!$lock->get()) {
            $this->warn('Ya hay una verificación en curso. Saltando ejecución.');
            Log::warning('[VerificarReglas] Ejecución anterior aún en curso, saltando.');
            return;
        }

        try {
            $this->ejecutar($notificador, $influx);
        } finally {
            $lock->release();
        }
    }

    private function ejecutar(NotificationService $notificador, InfluxService $influx): void
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

                // Fix: datos ausentes no evalúan la condición — evita falsas alarmas
                // por dispositivos desconectados o sin mediciones recientes.
                if ($valorActual === null) {
                    $this->line("[{$nombreUsuario}] {$regla->nombre} | {$dispositivo->nombre} = sin datos | omitido");
                    Log::info('Evaluación omitida: sin datos', [
                        'regla_id'    => $regla->id,
                        'dispositivo' => $dispositivo->nombre,
                    ]);
                    continue;
                }

                $condicionCumplida = $this->evaluarCondicion($valorActual, $regla->operador, $regla->valor_comparacion);

                $estado         = $dispositivo->pivot->estado_alerta ?? 'ok';
                $pendienteDesde = $dispositivo->pivot->pendiente_desde
                    ? Carbon::parse($dispositivo->pivot->pendiente_desde)
                    : null;

                $this->line("[{$nombreUsuario}] {$regla->nombre} | {$dispositivo->nombre} = {$valorActual} kWh | estado={$estado} | condición=" . ($condicionCumplida ? 'SÍ' : 'NO'));

                Log::info('Evaluando regla', [
                    'regla_id'           => $regla->id,
                    'dispositivo'        => $dispositivo->nombre,
                    'valor_actual'       => $valorActual,
                    'condicion_cumplida' => $condicionCumplida,
                    'estado'             => $estado,
                ]);

                switch ($estado) {
                    case 'ok':
                        if ($condicionCumplida) {
                            // Fix: cooldown post-resolución — evita flapping de notificaciones.
                            $ultimaResolucion = $dispositivo->pivot->ultima_resolucion_en
                                ? Carbon::parse($dispositivo->pivot->ultima_resolucion_en)
                                : null;

                            if ($ultimaResolucion) {
                                $minutosDesdeResolucion = $ultimaResolucion->diffInMinutes(Carbon::now());
                                if ($minutosDesdeResolucion < self::COOLDOWN_MINUTOS) {
                                    $restantes = self::COOLDOWN_MINUTOS - $minutosDesdeResolucion;
                                    $this->line("  → Cooldown activo, faltan {$restantes} min para poder volver a disparar.");
                                    break;
                                }
                            }

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
        Log::info('Fin de revisión de reglas', [
            'transcurrido_s'    => $transcurrido,
            'reglas_evaluadas'  => $reglas->count(),
        ]);
    }

    private function transicionPendiente(Regla $regla, Dispositivo $dispositivo): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'estado_alerta'   => 'pending',
            'pendiente_desde' => Carbon::now()->toDateTimeString(),
        ]);
        Log::info('Estado → pending', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionActiva(Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, float $valorActual): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'estado_alerta'     => 'firing',
            'pendiente_desde'   => null,
            'ultimo_disparo_en' => Carbon::now()->toDateTimeString(),
        ]);

        $this->info("  → FIRING: enviando alerta.");

        $mensaje = "🚨 Regla '{$regla->nombre}' activada en {$dispositivo->nombre} (valor={$valorActual} kWh)";

        $this->despacharAlerta('firing', $regla, $dispositivo, $notificador, $usuario, $valorActual, $mensaje);
        Log::info('Estado → firing', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionOk(Regla $regla, Dispositivo $dispositivo): void
    {
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'estado_alerta'   => 'ok',
            'pendiente_desde' => null,
        ]);
        Log::info('Estado → ok', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function transicionResuelta(Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, float $valorActual): void
    {
        // Fix: guardar timestamp de resolución para el cooldown.
        $regla->dispositivos()->updateExistingPivot($dispositivo->id, [
            'estado_alerta'        => 'ok',
            'pendiente_desde'      => null,
            'ultima_resolucion_en' => Carbon::now()->toDateTimeString(),
        ]);

        $this->info("  → RESUELTO: enviando notificación de resolución.");

        $mensaje = "✅ Regla '{$regla->nombre}' resuelta en {$dispositivo->nombre} (valor actual={$valorActual} kWh)";

        $this->despacharAlerta('resolution', $regla, $dispositivo, $notificador, $usuario, $valorActual, $mensaje);
        Log::info('Estado → ok (resolución)', ['regla_id' => $regla->id, 'dispositivo' => $dispositivo->nombre]);
    }

    private function despacharAlerta(string $tipo, Regla $regla, Dispositivo $dispositivo, NotificationService $notificador, ?User $usuario, float $valorActual, string $mensaje): void
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
        float $valorActual,
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

    private function interpolarPlantilla(string $plantilla, Regla $regla, Dispositivo $dispositivo, float $valorActual): string
    {
        $etiquetaValor = "{$valorActual} kWh";
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
            'user_id'            => $regla->user_id,
            'regla_id'           => $regla->id,
            'nombre_regla'       => $regla->nombre,
            'dispositivo_id'     => $dispositivo->id,
            'nombre_dispositivo' => $dispositivo->nombre,
            'tipo'               => $tipo,
            'canales'            => $canales ?: null,
            'mensaje'            => $mensaje,
        ]);
    }

    private function evaluarCondicion(float $valor, string $operador, $comparacion): bool
    {
        $vc = (float) $comparacion;

        return match ($operador) {
            '>'  => $valor >  $vc,
            '<'  => $valor <  $vc,
            '>=' => $valor >= $vc,
            '<=' => $valor <= $vc,
            '==' => abs($valor - $vc) < 1e-9,
            '!=' => abs($valor - $vc) >= 1e-9,
            default => false,
        };
    }
}
