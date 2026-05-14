<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use TelegramBot\Api\BotApi;
use Illuminate\Support\Facades\Http;

class NotificationMethodController extends Controller
{
    public function update(Request $request, $type)
    {
        $user = $request->user();

        /** ---------------- TELEGRAM ---------------- */
        if ($type === 'telegram') {
            $cred = $user->telegramCredential;

            // Toggle rápido
            if ($request->has('active') && !$request->has('chat_id')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Telegram antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Telegram actualizado.');
            }

            $request->merge(['active' => $request->has('active')]);
            $data = $request->validate([
                'chat_id'   => 'required|string',
                'bot_token' => 'required|string',
                'active'    => 'boolean',
            ]);

            // Probar primero; guardar solo si el test pasa
            try {
                $telegram = new BotApi($data['bot_token']);
                $telegram->sendMessage($data['chat_id'], '✅ Credenciales de Telegram configuradas correctamente.');
            } catch (\Exception $e) {
                Log::error("Error al enviar mensaje de prueba por Telegram: " . $e->getMessage());
                return back()->withErrors('Error al enviar mensaje de prueba. Revisa el token y el chat ID.');
            }

            $user->telegramCredential()->updateOrCreate([], [
                'chat_id'   => $data['chat_id'],
                'bot_token' => encrypt($data['bot_token']),
                'active'    => $data['active'],
            ]);

            return back()->with('status', 'Configuración de Telegram actualizada.');
        }

        /** ---------------- EMAIL ---------------- */
        if ($type === 'email') {
            $cred = $user->smtpCredential;

            // Toggle rápido
            if ($request->has('active') && !$request->has('smtp_host')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Email antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Email actualizado.');
            }

            $request->merge(['active' => $request->input('active') === '1']);
            $data = $request->validate([
                'from_address' => 'required|email',
                'smtp_host'    => 'required|string',
                'smtp_port'    => 'required|integer',
                'smtp_user'    => 'required|string',
                'smtp_pass'    => 'required|string',
                'active'       => 'boolean',
            ]);

            $config = $user->smtpCredential()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'host'         => $data['smtp_host'],
                    'port'         => $data['smtp_port'],
                    'username'     => $data['smtp_user'],
                    'from_address' => $data['from_address'],
                    'password'     => encrypt($data['smtp_pass']),
                    'encryption'   => 'tls',
                    'active'       => $data['active'],
                ]
            );

            if ($data['active']) {
                try {
                    config([
                        'mail.default'                    => 'smtp',
                        'mail.mailers.smtp.host'          => $config->host,
                        'mail.mailers.smtp.port'          => $config->port,
                        'mail.mailers.smtp.encryption'    => $config->encryption,
                        'mail.mailers.smtp.username'      => $config->username,
                        'mail.mailers.smtp.password'      => decrypt($config->password),
                        'mail.from.address'               => $data['from_address'],
                        'mail.from.name'                  => 'Tersime',
                    ]);

                    Mail::raw('✅ Configuración SMTP de Tersime verificada correctamente.', function ($message) use ($data) {
                        $message->to($data['from_address'])
                                ->from($data['from_address'], 'Tersime')
                                ->subject('✅ Tersime — Configuración de correo verificada');
                    });
                } catch (\Exception $e) {
                    Log::error('Error enviando correo de prueba: ' . $e->getMessage());
                    return back()->withErrors('No se pudo enviar el correo de prueba: ' . $e->getMessage());
                }
            }

            return back()->with('status', 'Configuración de correo actualizada.');
        }

        /** ---------------- DISCORD ---------------- */
        if ($type === 'discord') {
            $cred = $user->discordCredential;

            // Toggle rápido
            if ($request->has('active') && !$request->has('webhook_url')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Discord antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Discord actualizado.');
            }

            $request->merge(['active' => $request->input('active') === '1']);
            $data = $request->validate([
                'webhook_url' => 'required|url',
                'active'      => 'boolean',
            ]);

            $cred = $user->discordCredential()->updateOrCreate(
                ['user_id' => $user->id],
                ['webhook_url' => $data['webhook_url'], 'active' => $data['active']]
            );

            if ($data['active']) {
                try {
                    $response = Http::post($cred->webhook_url, [
                        'content' => '✅ Configuración de Discord guardada correctamente.',
                    ]);
                    if ($response->failed()) {
                        throw new \Exception("Discord respondió HTTP " . $response->status());
                    }
                } catch (\Exception $e) {
                    Log::error('Error enviando prueba a Discord: ' . $e->getMessage());
                    return back()->withErrors('No se pudo enviar el mensaje de prueba a Discord: ' . $e->getMessage());
                }
            }

            return back()->with('status', 'Configuración de Discord actualizada.');
        }

        return back()->withErrors('Canal no reconocido.');
    }

    public function sendEmail(string $text, User $user, string $recipientEmail, string $fromAddress = null)
    {
        $cred = $user->smtpCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene SMTP configurado o activo.");
        }

        $from = $fromAddress ?? $cred->from_address ?? $cred->username;

        if (empty($from)) {
            throw new \Exception("No hay dirección de remitente válida.");
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $cred->host,
            'mail.mailers.smtp.port'       => $cred->port,
            'mail.mailers.smtp.encryption' => $cred->encryption,
            'mail.mailers.smtp.username'   => $cred->username,
            'mail.mailers.smtp.password'   => decrypt($cred->password),
            'mail.from.address'            => $from,
            'mail.from.name'               => 'Tersime',
        ]);

        \Mail::raw($text, function ($message) use ($recipientEmail, $from) {
            $message->to($recipientEmail)
                    ->from($from, 'Tersime')
                    ->subject('📩 Notificación del sistema');
        });

        Log::info("Correo enviado a {$recipientEmail} para usuario {$user->id}");
    }

    public function sendTelegram(string $text, User $user)
    {
        $cred = $user->telegramCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Telegram configurado o activo.");
        }

        $telegram = new BotApi(decrypt($cred->bot_token));
        $telegram->sendMessage($cred->chat_id, $text);

        Log::info("Telegram enviado para usuario {$user->id}");
    }

    public function sendDiscord(string $text, User $user)
    {
        $cred = $user->discordCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Discord configurado o activo.");
        }

        $response = Http::post($cred->webhook_url, ['content' => $text]);

        if ($response->failed()) {
            throw new \Exception("Discord rechazó el mensaje: HTTP " . $response->status());
        }

        Log::info("Discord enviado para usuario {$user->id}");
    }

    public function sendEmailWithAttachment(string $text, User $user, string $recipientEmail, string $pdfPath = null, string $fromAddress = null)
    {
        $cred = $user->smtpCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene SMTP configurado o activo.");
        }

        $from = $fromAddress ?? $cred->from_address ?? $cred->username;

        if (empty($from)) {
            throw new \Exception("No hay dirección de remitente válida.");
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $cred->host,
            'mail.mailers.smtp.port'       => $cred->port,
            'mail.mailers.smtp.encryption' => $cred->encryption,
            'mail.mailers.smtp.username'   => $cred->username,
            'mail.mailers.smtp.password'   => decrypt($cred->password),
            'mail.from.address'            => $from,
            'mail.from.name'               => 'Tersime',
        ]);

        \Mail::send([], [], function ($message) use ($recipientEmail, $from, $text, $pdfPath) {
            $message->to($recipientEmail)
                    ->from($from, 'Tersime')
                    ->subject('📩 Notificación del sistema')
                    ->setBody($text, 'text/plain');

            if ($pdfPath && file_exists($pdfPath)) {
                $message->attach($pdfPath, ['mime' => 'application/pdf']);
            }
        });

        Log::info("Correo con adjunto enviado a {$recipientEmail} para usuario {$user->id}");
    }

    public function sendTelegramWithAttachment(string $text, User $user, string $pdfPath = null)
    {
        $cred = $user->telegramCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Telegram configurado o activo.");
        }

        $telegram = new BotApi(decrypt($cred->bot_token));

        if ($pdfPath) {
            $real = realpath($pdfPath);
            if ($real === false || !is_file($real) || !is_readable($real)) {
                throw new \InvalidArgumentException("La ruta del PDF no existe o no es legible: {$pdfPath}");
            }

            $caption = mb_substr($text, 0, 1024);
            $file    = new \CURLFile($real, 'application/pdf', basename($real));
            $telegram->sendDocument($cred->chat_id, $file, $caption);

            if (mb_strlen($text) > 1024) {
                $telegram->sendMessage($cred->chat_id, $text);
            }
        } else {
            $telegram->sendMessage($cred->chat_id, $text);
        }

        Log::info("Telegram con adjunto enviado para usuario {$user->id}");
    }

    public function sendDiscordWithFile(string $message, string $filePath, User $user)
    {
        $cred = $user->discordCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Discord configurado o activo.");
        }

        if (!file_exists($filePath)) {
            throw new \Exception("El archivo no existe: {$filePath}");
        }

        $response = Http::attach('file', file_get_contents($filePath), basename($filePath))
            ->post($cred->webhook_url, ['content' => $message]);

        if ($response->failed()) {
            throw new \Exception("Error enviando archivo a Discord: HTTP " . $response->status());
        }

        Log::info("Discord con archivo enviado para usuario {$user->id}");
    }
}
