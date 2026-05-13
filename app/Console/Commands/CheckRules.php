<?php

namespace App\Console\Commands;

use App\Http\Controllers\GrafanaController;
use Illuminate\Console\Command;
use App\Models\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\NotificationMethodController;

class CheckRules extends Command
{
    protected $signature = 'rules:check';
    protected $description = 'Revisar todas las reglas activas y enviar notificaciones si se cumplen';

    public function handle(NotificationMethodController $notifier)
    {
        $this->info('=== Inicio de revisión de reglas ===');
        Log::info('Inicio de revisión de reglas');

        $rules = Rule::with(['dispositivos', 'user'])
            ->where('is_active', true)
            ->get();

        // Obtenemos los dispositivos desde Grafana (estructura esperada: ['devices' => [...]] o directamente [...])
        $dispositivosGrafanaRaw = GrafanaController::checkDevices();
        $grafanaDevices = $dispositivosGrafanaRaw['devices'] ?? $dispositivosGrafanaRaw;
        if (!is_array($grafanaDevices)) {
            $grafanaDevices = [];
        }

        foreach ($rules as $rule) {
            $user = $rule->user;
            $username = $user ? $user->name : 'Desconocido';

            foreach ($rule->dispositivos as $dispositivo) {

                // Buscamos el dispositivo en la lista de Grafana por name o por dev_eui
                $found = null;
                foreach ($grafanaDevices as $gd) {
                    // admitir diferentes keys que puedan venir del checkDevices
                    $gdName = $gd['name'] ?? $gd['device_name'] ?? null;
                    $gdEui = $gd['dev_eui'] ?? null;

                    if ($gdName === $dispositivo->name || ($gdEui && $gdEui === ($dispositivo->dev_eui ?? null))) {
                        $found = $gd;
                        break;
                    }
                }

                // Si no está o no tiene valor -> pérdida de comunicación
                if (!$found || !array_key_exists('value', $found) || $found['value'] === null) {
                    $this->warn("❌ [{$username}] No ha sido posible comunicarse con el dispositivo {$dispositivo->nombre}");
                    Log::warning("Dispositivo sin comunicación", [
                        'rule_id' => $rule->id,
                        'device_id' => $dispositivo->id,
                        'device_name' => $dispositivo->name,
                        'device_dev_eui' => $dispositivo->dev_eui ?? null,
                    ]);

                    $textoMissing = "🚨 No ha sido posible comunicarse con el dispositivo {$dispositivo->nombre}";

                    // Enviar notificaciones configuradas en la regla
                    // Email (si está habilitado y hay destinatario)
                    if ($rule->email_enabled && $rule->recipient_email) {
                        try {
                            $notifier->sendEmail($textoMissing, $user, $rule->recipient_email);
                            Log::info("Email (sin comunicación) enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                                'device_name' => $dispositivo->nombre,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando EMAIL (sin comunicación): " . $e->getMessage());
                            Log::error("Error enviando EMAIL (sin comunicación): " . $e->getMessage());
                        }
                    }

                    // Telegram (usar siempre el texto explícito de error, NO plantilla)
                    if ($rule->telegram_enabled) {
                        try {
                            $notifier->sendTelegram($textoMissing, $user);
                            Log::info("Telegram (sin comunicación) enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                                'device_name' => $dispositivo->nombre,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando TELEGRAM (sin comunicación): " . $e->getMessage());
                            Log::error("Error enviando TELEGRAM (sin comunicación): " . $e->getMessage());
                        }
                    }

                    // Discord (usar siempre el texto explícito de error, NO plantilla)
                    if ($rule->discord_enabled) {
                        try {
                            $notifier->sendDiscord($textoMissing, $user);
                            Log::info("Discord (sin comunicación) enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                                'device_name' => $dispositivo->nombre,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando DISCORD (sin comunicación): " . $e->getMessage());
                            Log::error("Error enviando DISCORD (sin comunicación): " . $e->getMessage());
                        }
                    }

                    // Pasamos al siguiente dispositivo
                    continue;
                }

                // Si está y tiene value, lo usamos como currentValue
                $rawValue = $found['value'];
                $currentValue = is_numeric($rawValue) ? (float) $rawValue : null;

                // Si value no es numérico -> tratamos como pérdida de comunicación
                if ($currentValue === null) {
                    $this->warn("❌ [{$username}] Valor no numérico para {$dispositivo->nombre}, se considera sin comunicación");
                    Log::warning("Valor no numérico en grafana", [
                        'rule_id' => $rule->id,
                        'device_name' => $dispositivo->name,
                        'raw_value' => $rawValue,
                    ]);

                    $textoMissing = "🚨 No ha sido posible comunicarse con el dispositivo {$dispositivo->nombre} (valor inválido)";

                    if ($rule->email_enabled && $rule->recipient_email) {
                        try {
                            $notifier->sendEmail($textoMissing, $user, $rule->recipient_email);
                        } catch (\Exception $e) {
                            Log::error("Error enviando EMAIL (valor inválido): " . $e->getMessage());
                        }
                    }

                    if ($rule->telegram_enabled) {
                        try {
                            $notifier->sendTelegram($textoMissing, $user);
                        } catch (\Exception $e) {
                            Log::error("Error enviando TELEGRAM (valor inválido): " . $e->getMessage());
                        }
                    }

                    if ($rule->discord_enabled) {
                        try {
                            $notifier->sendDiscord($textoMissing, $user);
                        } catch (\Exception $e) {
                            Log::error("Error enviando DISCORD (valor inválido): " . $e->getMessage());
                        }
                    }

                    continue;
                }

                // Log y salida informativa usando el valor real de Grafana
                $this->info("👤 Usuario: {$username} | Regla {$rule->name} en {$dispositivo->nombre} (valor={$currentValue})");
                Log::info("Evaluando regla", [
                    'user_id' => $rule->user_id,
                    'username' => $username,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'device_id' => $dispositivo->id,
                    'device_name' => $dispositivo->nombre,
                    'current_value' => $currentValue,
                ]);

                if ($this->evaluateRule($currentValue, $rule->operator, $rule->comparison_value)) {
                    $this->info("✅ [{$username}] Regla {$rule->name} cumplida para {$dispositivo->nombre}");

                    $texto = $rule->template_email ?? "🚨 Regla '{$rule->name}' activada en {$dispositivo->nombre} (valor={$currentValue})";

                    // Email
                    if ($rule->email_enabled && $rule->recipient_email) {
                        try {
                            $notifier->sendEmail($texto, $user, $rule->recipient_email);
                            Log::info("Email enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando EMAIL: " . $e->getMessage());
                            Log::error("Error enviando EMAIL: " . $e->getMessage());
                        }
                    }

                    // Telegram
                    if ($rule->telegram_enabled) {
                        try {
                            $textoTelegram = $rule->template_telegram ?? $texto;
                            $notifier->sendTelegram($textoTelegram, $user);
                            Log::info("Telegram enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando TELEGRAM: " . $e->getMessage());
                            Log::error("Error enviando TELEGRAM: " . $e->getMessage());
                        }
                    }

                    // Discord
                    if ($rule->discord_enabled) {
                        try {
                            $textoDiscord = $rule->template_discord ?? $texto;
                            $notifier->sendDiscord($textoDiscord, $user);
                            Log::info("Discord enviado correctamente", [
                                'rule_id' => $rule->id,
                                'user_id' => $user ? $user->id : null,
                            ]);
                        } catch (\Exception $e) {
                            $this->error("❌ Error enviando DISCORD: " . $e->getMessage());
                            Log::error("Error enviando DISCORD: " . $e->getMessage());
                        }
                    }
                } else {
                    $this->warn("⚠️ [{$username}] Regla {$rule->name} NO cumplida para {$dispositivo->name}");
                }
            }
        }

        $this->info('=== Fin de revisión de reglas ===');
        Log::info('Fin de revisión de reglas');
    }

    private function evaluateRule($currentValue, $operator, $comparisonValue)
    {
        $cv = is_numeric($comparisonValue) ? (float) $comparisonValue : $comparisonValue;

        switch ($operator) {
            case '>': return $currentValue > $cv;
            case '<': return $currentValue < $cv;
            case '==': return $currentValue == $cv;
            case '!=': return $currentValue != $cv;
            case '>=': return $currentValue >= $cv;
            case '<=': return $currentValue <= $cv;
            default: return false;
        }
    }
}
