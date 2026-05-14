<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Rule;
use App\Http\Controllers\InfluxController;
use App\Http\Controllers\NotificationMethodController;
use Illuminate\Support\Facades\Log;

class CheckRules extends Command
{
    protected $signature   = 'rules:check';
    protected $description = 'Revisar todas las reglas activas y enviar notificaciones si se cumplen';

    public function handle(NotificationMethodController $notifier, InfluxController $influx)
    {
        $this->info('=== Inicio de revisión de reglas ===');
        Log::info('Inicio de revisión de reglas');

        $rules = Rule::with(['dispositivos', 'user'])
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            $user     = $rule->user;
            $username = $user ? $user->name : 'Desconocido';

            foreach ($rule->dispositivos as $dispositivo) {
                $tag = $dispositivo->influx_tag;

                // --- Obtener último valor desde InfluxDB ---
                $currentValue = $influx->ultimoValor($tag);

                if ($currentValue === null) {
                    $this->warn("[{$username}] Sin datos en las últimas 24h para {$dispositivo->nombre}");
                    Log::warning('Dispositivo sin datos recientes', [
                        'rule_id'     => $rule->id,
                        'influx_tag'  => $tag,
                        'device_name' => $dispositivo->nombre,
                    ]);

                    // Aplicar el mismo cooldown para no spamear si el dispositivo lleva días sin datos
                    if ($rule->last_triggered_at !== null) {
                        $dias = $rule->last_triggered_at->diffInDays(Carbon::now());
                        if ($dias < $rule->time_range) {
                            $this->line("  → Sin datos pero en cooldown ({$dias}/{$rule->time_range} días).");
                            continue;
                        }
                    }

                    $texto = "🚨 Sin datos en las últimas 24 h para el dispositivo {$dispositivo->nombre}";
                    $this->enviarNotificaciones($notifier, $rule, $user, $texto);

                    $rule->last_triggered_at = Carbon::now();
                    $rule->save();
                    continue;
                }

                $this->info("[{$username}] Regla '{$rule->name}' — {$dispositivo->nombre} = {$currentValue}");
                Log::info('Evaluando regla', [
                    'rule_id'       => $rule->id,
                    'rule_name'     => $rule->name,
                    'influx_tag'    => $tag,
                    'current_value' => $currentValue,
                ]);

                if (!$this->evaluarCondicion($currentValue, $rule->operator, $rule->comparison_value)) {
                    $this->line("  → Condición no cumplida, sin notificación.");
                    continue;
                }

                // --- Cooldown: no volver a notificar hasta pasados time_range días ---
                if ($rule->last_triggered_at !== null) {
                    $diasDesdeUltimo = $rule->last_triggered_at->diffInDays(Carbon::now());
                    if ($diasDesdeUltimo < $rule->time_range) {
                        $this->line("  → Condición cumplida pero en cooldown ({$diasDesdeUltimo}/{$rule->time_range} días).");
                        Log::info('Regla en cooldown', [
                            'rule_id'          => $rule->id,
                            'last_triggered_at' => $rule->last_triggered_at->toISOString(),
                            'dias_restantes'   => $rule->time_range - $diasDesdeUltimo,
                        ]);
                        continue;
                    }
                }

                $this->info("  → Condición cumplida, enviando notificaciones.");

                $textoPorDefecto = "🚨 Regla '{$rule->name}' activada en {$dispositivo->nombre} (valor={$currentValue} kWh)";

                $this->enviarNotificaciones($notifier, $rule, $user, $textoPorDefecto);

                $rule->last_triggered_at = Carbon::now();
                $rule->save();
            }
        }

        $this->info('=== Fin de revisión de reglas ===');
        Log::info('Fin de revisión de reglas');
    }

    private function enviarNotificaciones(NotificationMethodController $notifier, Rule $rule, $user, string $textoPorDefecto)
    {
        if ($rule->email_enabled && $rule->recipient_email && $user) {
            try {
                $notifier->sendEmail($rule->template_email ?: $textoPorDefecto, $user, $rule->recipient_email);
                Log::info('Email enviado', ['rule_id' => $rule->id]);
            } catch (\Exception $e) {
                $this->error("Error email: " . $e->getMessage());
                Log::error('Error enviando email', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }

        if ($rule->telegram_enabled && $user) {
            try {
                $notifier->sendTelegram($rule->template_telegram ?: $textoPorDefecto, $user);
                Log::info('Telegram enviado', ['rule_id' => $rule->id]);
            } catch (\Exception $e) {
                $this->error("Error telegram: " . $e->getMessage());
                Log::error('Error enviando telegram', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }

        if ($rule->discord_enabled && $user) {
            try {
                $notifier->sendDiscord($rule->template_discord ?: $textoPorDefecto, $user);
                Log::info('Discord enviado', ['rule_id' => $rule->id]);
            } catch (\Exception $e) {
                $this->error("Error discord: " . $e->getMessage());
                Log::error('Error enviando discord', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function evaluarCondicion(float $valor, string $operador, $comparacion): bool
    {
        $cv = is_numeric($comparacion) ? (float) $comparacion : $comparacion;

        switch ($operador) {
            case '>':  return $valor >  $cv;
            case '<':  return $valor <  $cv;
            case '>=': return $valor >= $cv;
            case '<=': return $valor <= $cv;
            case '==': return $valor == $cv;
            case '!=': return $valor != $cv;
            default:   return false;
        }
    }
}
