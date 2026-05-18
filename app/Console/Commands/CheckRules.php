<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Rule;
use App\Models\AlertLog;
use App\Services\InfluxService;
use App\Services\NotificationService;
use App\Notifications\NotificacionAlerta;
use Illuminate\Support\Facades\Log;

class CheckRules extends Command
{
    protected $signature   = 'rules:check';
    protected $description = 'Revisar reglas activas y actualizar estados de alerta (ok / pending / firing)';

    public function handle(NotificationService $notifier, InfluxService $influx)
    {
        $start = microtime(true);
        $this->info('=== Inicio de revisión de reglas ===');
        Log::info('Inicio de revisión de reglas');

        $rules = Rule::with(['dispositivos', 'user'])
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            $user     = $rule->user;
            $username = $user ? $user->name : 'Desconocido';

            foreach ($rule->dispositivos as $dispositivo) {
                $tag          = $dispositivo->influx_tag;
                $currentValue = $influx->ultimoValor($tag);

                $conditionMet = ($currentValue === null)
                    || $this->evaluarCondicion($currentValue, $rule->operator, $rule->comparison_value);

                $state        = $dispositivo->pivot->alert_state ?? 'ok';
                $pendingSince = $dispositivo->pivot->pending_since
                    ? Carbon::parse($dispositivo->pivot->pending_since)
                    : null;

                $valueLabel = $currentValue === null ? 'sin datos' : "{$currentValue} kWh";
                $this->line("[{$username}] {$rule->name} | {$dispositivo->nombre} = {$valueLabel} | estado={$state} | condición=" . ($conditionMet ? 'SÍ' : 'NO'));

                Log::info('Evaluando regla', [
                    'rule_id'       => $rule->id,
                    'device'        => $dispositivo->nombre,
                    'current_value' => $currentValue,
                    'condition_met' => $conditionMet,
                    'state'         => $state,
                ]);

                switch ($state) {
                    case 'ok':
                        if ($conditionMet) {
                            if ($rule->for_duration === 0) {
                                $this->transitionFiring($rule, $dispositivo, $notifier, $user, $currentValue);
                            } else {
                                $this->transitionPending($rule, $dispositivo);
                                $this->line("  → Condición cumplida, esperando ventana de {$rule->for_duration} min.");
                            }
                        }
                        break;

                    case 'pending':
                        if ($conditionMet) {
                            $minutes = $pendingSince ? $pendingSince->diffInMinutes(Carbon::now()) : 0;
                            if ($minutes >= $rule->for_duration) {
                                $this->transitionFiring($rule, $dispositivo, $notifier, $user, $currentValue);
                            } else {
                                $remaining = $rule->for_duration - $minutes;
                                $this->line("  → Pendiente, faltan {$remaining} min para confirmar.");
                            }
                        } else {
                            $this->transitionOk($rule, $dispositivo);
                            $this->line("  → Condición ya no cumplida, reseteo a OK (falsa alarma).");
                        }
                        break;

                    case 'firing':
                        if (!$conditionMet) {
                            $this->transitionOkResolution($rule, $dispositivo, $notifier, $user, $currentValue);
                        } else {
                            $this->line("  → Sigue en FIRING, sin nueva notificación.");
                        }
                        break;
                }
            }
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("=== Fin de revisión de reglas ({$elapsed}s) ===");
        Log::info('Fin de revisión de reglas', ['elapsed_s' => $elapsed, 'rules_evaluated' => $rules->count()]);
    }

    private function transitionPending(Rule $rule, $dispositivo): void
    {
        $rule->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'pending',
            'pending_since' => Carbon::now()->toDateTimeString(),
        ]);
        Log::info('Estado → pending', ['rule_id' => $rule->id, 'device' => $dispositivo->nombre]);
    }

    private function transitionFiring(Rule $rule, $dispositivo, NotificationService $notifier, $user, ?float $currentValue): void
    {
        $rule->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'       => 'firing',
            'pending_since'     => null,
            'last_triggered_at' => Carbon::now()->toDateTimeString(),
        ]);

        $this->info("  → FIRING: enviando alerta.");

        $textoDefault = $currentValue === null
            ? "🚨 Sin datos en las últimas 24 h para {$dispositivo->nombre} (regla: {$rule->name})"
            : "🚨 Regla '{$rule->name}' activada en {$dispositivo->nombre} (valor={$currentValue} kWh)";

        $this->enviarNotificaciones($notifier, $rule, $user, $dispositivo, $currentValue, $textoDefault);
        $this->registrarLog($rule, $dispositivo, 'firing', $textoDefault);

        if ($user) {
            try {
                $user->notify(new NotificacionAlerta('firing', $rule->name, $dispositivo->nombre, $textoDefault));
            } catch (\Throwable $e) {
                Log::error('Error guardando notificación DB (firing)', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Estado → firing', ['rule_id' => $rule->id, 'device' => $dispositivo->nombre]);
    }

    private function transitionOk(Rule $rule, $dispositivo): void
    {
        $rule->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'ok',
            'pending_since' => null,
        ]);
        Log::info('Estado → ok', ['rule_id' => $rule->id, 'device' => $dispositivo->nombre]);
    }

    private function transitionOkResolution(Rule $rule, $dispositivo, NotificationService $notifier, $user, ?float $currentValue): void
    {
        $rule->dispositivos()->updateExistingPivot($dispositivo->id, [
            'alert_state'   => 'ok',
            'pending_since' => null,
        ]);

        $this->info("  → RESUELTO: enviando notificación de resolución.");

        $valueLabel   = $currentValue === null ? 'sin datos' : "{$currentValue} kWh";
        $textoDefault = "✅ Regla '{$rule->name}' resuelta en {$dispositivo->nombre} (valor actual={$valueLabel})";

        $this->enviarNotificaciones($notifier, $rule, $user, $dispositivo, $currentValue, $textoDefault);
        $this->registrarLog($rule, $dispositivo, 'resolution', $textoDefault);

        if ($user) {
            try {
                $user->notify(new NotificacionAlerta('resolution', $rule->name, $dispositivo->nombre, $textoDefault));
            } catch (\Throwable $e) {
                Log::error('Error guardando notificación DB (resolution)', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Estado → ok (resolución)', ['rule_id' => $rule->id, 'device' => $dispositivo->nombre]);
    }

    private function enviarNotificaciones(
        NotificationService $notifier,
        Rule $rule,
        $user,
        $dispositivo,
        ?float $currentValue,
        string $textoPorDefecto
    ): void {
        if ($rule->email_enabled && $rule->recipient_email && $user) {
            $msg = $rule->template_email
                ? $this->interpolateTemplate($rule->template_email, $rule, $dispositivo, $currentValue)
                : $textoPorDefecto;
            try {
                $notifier->sendEmail($msg, $user, $rule->recipient_email);
                Log::info('Email enviado', ['rule_id' => $rule->id]);
            } catch (\Throwable $e) {
                $this->error("Error email: " . $e->getMessage());
                Log::error('Error enviando email', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }

        if ($rule->telegram_enabled && $user) {
            $msg = $rule->template_telegram
                ? $this->interpolateTemplate($rule->template_telegram, $rule, $dispositivo, $currentValue)
                : $textoPorDefecto;
            try {
                $notifier->sendTelegram($msg, $user);
                Log::info('Telegram enviado', ['rule_id' => $rule->id]);
            } catch (\Throwable $e) {
                $this->error("Error telegram: " . $e->getMessage());
                Log::error('Error enviando telegram', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }

        if ($rule->discord_enabled && $user) {
            $msg = $rule->template_discord
                ? $this->interpolateTemplate($rule->template_discord, $rule, $dispositivo, $currentValue)
                : $textoPorDefecto;
            try {
                $notifier->sendDiscord($msg, $user);
                Log::info('Discord enviado', ['rule_id' => $rule->id]);
            } catch (\Throwable $e) {
                $this->error("Error discord: " . $e->getMessage());
                Log::error('Error enviando discord', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function interpolateTemplate(string $template, Rule $rule, $dispositivo, ?float $currentValue): string
    {
        $valueLabel = $currentValue === null ? 'sin datos' : "{$currentValue} kWh";
        return str_replace(
            ['{dispositivo}', '{device}', '{regla}',    '{rule}',      '{valor}',    '{value}'],
            [$dispositivo->nombre, $dispositivo->nombre, $rule->name, $rule->name, $valueLabel, $valueLabel],
            $template
        );
    }

    private function registrarLog(Rule $rule, $dispositivo, string $type, string $message): void
    {
        $channels = collect(['telegram', 'email', 'discord'])
            ->filter(fn($ch) => $rule->{"{$ch}_enabled"})
            ->values()
            ->toArray();

        AlertLog::create([
            'user_id'        => $rule->user_id,
            'rule_id'        => $rule->id,
            'rule_name'      => $rule->name,
            'dispositivo_id' => $dispositivo->id,
            'device_name'    => $dispositivo->nombre,
            'type'           => $type,
            'channels'       => $channels ?: null,
            'message'        => $message,
        ]);
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
