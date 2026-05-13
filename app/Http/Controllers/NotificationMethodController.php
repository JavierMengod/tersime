<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeUser;
use Illuminate\Http\Request;
use App\Models\NotificationMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\UserMailer;
use App\Models\User;
use TelegramBot\Api\BotApi;
use Illuminate\Support\Facades\Http;

class NotificationMethodController extends Controller
{
    public function edit()
    {
        $defaultChannels = ['telegram', 'email', 'discord'];
        $methods = NotificationMethod::whereIn('channel', $defaultChannels)
            ->get()
            ->keyBy('channel');

        foreach ($defaultChannels as $ch) {
            if (!$methods->has($ch)) {
                $methods->put($ch, new NotificationMethod([
                    'channel' => $ch,
                    'active' => false,
                    'config' => [],
                ]));
            }
        }

        return view('settings.notifications', compact('methods'));
    }

    public function send()
    {
        $chatId = '6336830304';
        $botToken = 'TU_TOKEN_AQUI';

        try {
            $telegram = new BotApi($botToken);
            $response = $telegram->sendMessage($chatId, '¡Hola desde Laravel!');
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error("Error enviando mensaje: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo enviar el mensaje.'], 500);
        }
    }

    public function update(Request $request, $type)
    {
        $user = $request->user();

        /** ---------------- TELEGRAM ---------------- */
        if ($type === 'telegram') {
            $cred = $user->telegramCredential;

            // Toggle rápido: viene solo "active" sin credenciales
            if ($request->has('active') && !$request->has('chat_id')) {
                if (!$cred) {
                    return back()->withErrors('Primero configura Telegram antes de activar/desactivar.');
                }
                $cred->update(['active' => $request->input('active') == 1]);
                return back()->with('status', 'Estado de Telegram actualizado.');
            }

            // Configuración completa (modal)
            $request->merge([
                'active' => $request->has('active'),
            ]);

            $rules = [
                'chat_id' => 'required|string',
                'bot_token' => 'required|string',
                'active' => 'boolean',
            ];
            $data = $request->validate($rules);

            $user->telegramCredential()->updateOrCreate([], [
                'chat_id' => $data['chat_id'],
                'bot_token' => encrypt($data['bot_token']),
                'active' => $data['active'],
            ]);

            try {
                $telegram = new BotApi($data['bot_token']);
                $telegram->sendMessage($data['chat_id'], '✅ Credenciales de Telegram configuradas correctamente.');
            } catch (\Exception $e) {
                Log::error("Error al enviar mensaje de prueba por Telegram: " . $e->getMessage());
                return back()->withErrors('Error al enviar mensaje de prueba por Telegram.');
            }

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

            // Configuración completa
            $request->merge([
                'active' => $request->input('active') === '1',
            ]);

            $rules = [
                'from_address' => 'required|email',
                'smtp_host' => 'required|string',
                'smtp_port' => 'required|integer',
                'smtp_user' => 'required|string',
                'smtp_pass' => 'required|string',
                'active' => 'boolean',
            ];
            $data = $request->validate($rules);

            $config = $user->smtpCredential()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'host' => $data['smtp_host'],
                    'port' => $data['smtp_port'],
                    'username' => $data['smtp_user'],
                    'password' => encrypt($data['smtp_pass']),
                    'encryption' => 'tls',
                    'active' => $data['active'],
                ]
            );

            // Envío de prueba si está activo
            if ($data['active']) {
                try {
                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp.host' => $config->host,
                        'mail.mailers.smtp.port' => $config->port,
                        'mail.mailers.smtp.encryption' => $config->encryption,
                        'mail.mailers.smtp.username' => $config->username,
                        'mail.mailers.smtp.password' => decrypt($config->password),
                        'mail.from.address' => $data['from_address'],
                        'mail.from.name' => config('mail.from.name'),
                    ]);

                    Mail::mailer('smtp')
                        ->to('jmengod10@gmail.com')
                        ->send(new WelcomeUser());
                } catch (\Exception $e) {
                    Log::error('Error enviando WelcomeUser: ' . $e->getMessage());
                    return back()->withErrors('No se pudo enviar el correo de prueba.');
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

            // Configuración completa
            $request->merge([
                'active' => $request->input('active') === '1',
            ]);

            $rules = [
                'webhook_url' => 'required|url',
                'active' => 'boolean',
            ];
            $data = $request->validate($rules);

            $cred = $user->discordCredential()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'webhook_url' => $data['webhook_url'],
                    'active' => $data['active'],
                ]
            );

            if ($data['active']) {
                try {
                    Http::post($cred->webhook_url, [
                        'content' => '✅ Configuración de Discord guardada correctamente.',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error enviando prueba a Discord: ' . $e->getMessage());
                    return back()->withErrors('No se pudo enviar el mensaje de prueba a Discord.');
                }
            }

            return back()->with('status', 'Configuración de Discord actualizada.');
        }

        return back()->withErrors('Error al actualizar credenciales');
    }

    public function sendAlert(UserMailer $userMailer)
    {
        $userId = 1;
        $destino = 'jmengod10@gmail.com';
        $mailable = new WelcomeUser();

        try {
            $userMailer->sendUsingUser($userId, $destino, $mailable);
            return back()->with('status', 'Correo enviado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors($e->getMessage());
        }
    }

    /**
     * Enviar correo usando credenciales SMTP del usuario
     */
    public function sendEmail(string $text, User $user, string $recipientEmail, string $fromAddress = null)
    {
        $cred = $user->smtpCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene SMTP configurado o activo.");
        }

        try {
            // Usar el fromAddress pasado o, si no, el username del SMTP
            $from = $fromAddress ?? $cred->username;

            if (empty($from)) {
                throw new \Exception("No hay dirección de remitente válida para enviar el correo.");
            }

            // Configuración dinámica del mailer
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $cred->host,
                'mail.mailers.smtp.port' => $cred->port,
                'mail.mailers.smtp.encryption' => $cred->encryption,
                'mail.mailers.smtp.username' => $cred->username,
                'mail.mailers.smtp.password' => decrypt($cred->password),
                'mail.from.address' => $from,
                'mail.from.name' => 'Tersime',
            ]);

            // Enviar mensaje plano
            \Mail::raw($text, function ($message) use ($recipientEmail, $from) {
                $message->to($recipientEmail)
                    ->from($from, 'Tersime')
                    ->subject('📩 Notificación del sistema');
            });

            \Log::info("Correo enviado correctamente a {$recipientEmail} para el usuario {$user->id}");

        } catch (\Exception $e) {
            \Log::error("Error enviando correo: " . $e->getMessage());
            throw $e;
        }
    }


    /**
     * Enviar mensaje por Telegram usando credenciales del usuario
     */
    public function sendTelegram(string $text, User $user)
    {
        $cred = $user->telegramCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Telegram configurado o activo.");
        }

        try {
            $telegram = new \TelegramBot\Api\BotApi(decrypt($cred->bot_token));
            $telegram->sendMessage($cred->chat_id, $text);

            \Log::info("Mensaje de Telegram enviado correctamente para el usuario {$user->id}");
        } catch (\Exception $e) {
            \Log::error("Error enviando mensaje de Telegram: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar mensaje por Discord usando credenciales del usuario
     */
    public function sendDiscord(string $text, User $user)
    {
        $cred = $user->discordCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Discord configurado o activo.");
        }

        try {
            \Http::post($cred->webhook_url, [
                'content' => $text,
            ]);

            \Log::info("Mensaje de Discord enviado correctamente para el usuario {$user->id}");
        } catch (\Exception $e) {
            \Log::error("Error enviando mensaje de Discord: " . $e->getMessage());
            throw $e;
        }
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

        try {
            $response = Http::attach(
                'file',                // nombre del campo
                file_get_contents($filePath),
                basename($filePath)    // nombre con el que se adjunta
            )->post($cred->webhook_url, [
                        'content' => $message,
                    ]);

            if ($response->failed()) {
                Log::error("Error enviando PDF a Discord: " . $response->body());
                throw new \Exception("Falló el envío a Discord.");
            }

            Log::info("Mensaje y archivo PDF enviados correctamente a Discord para el usuario {$user->id}");
        } catch (\Exception $e) {
            Log::error("Error enviando PDF a Discord: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar correo con texto y opcionalmente un PDF adjunto
     */
    public function sendEmailWithAttachment(string $text, User $user, string $recipientEmail, string $pdfPath = null, string $fromAddress = null)
    {
        $cred = $user->smtpCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene SMTP configurado o activo.");
        }

        try {
            $from = $fromAddress ?? $cred->username;

            if (empty($from)) {
                throw new \Exception("No hay dirección de remitente válida para enviar el correo.");
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $cred->host,
                'mail.mailers.smtp.port' => $cred->port,
                'mail.mailers.smtp.encryption' => $cred->encryption,
                'mail.mailers.smtp.username' => $cred->username,
                'mail.mailers.smtp.password' => decrypt($cred->password),
                'mail.from.address' => $from,
                'mail.from.name' => 'Tersime',
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

            \Log::info("Correo enviado correctamente a {$recipientEmail} para el usuario {$user->id}");

        } catch (\Exception $e) {
            \Log::error("Error enviando correo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar mensaje por Telegram con texto y opcionalmente un PDF adjunto
     */
    public function sendTelegramWithAttachment(string $text, User $user, string $pdfPath = null)
    {
        $cred = $user->telegramCredential;

        if (!$cred || !$cred->active) {
            throw new \Exception("El usuario no tiene Telegram configurado o activo.");
        }

        try {
            $telegram = new \TelegramBot\Api\BotApi(decrypt($cred->bot_token));

            if ($pdfPath) {
                $real = realpath($pdfPath);
                if ($real === false || !is_file($real) || !is_readable($real)) {
                    throw new \InvalidArgumentException("La ruta del PDF no existe o no es legible: {$pdfPath}");
                }

                // Enviar como archivo subido (InputFile) usando CURLFile
                $caption = mb_substr($text, 0, 1024); // límite de Telegram para caption en documentos
                $file = new \CURLFile($real, 'application/pdf', basename($real));

                $telegram->sendDocument(
                    $cred->chat_id,
                    $file,        // documento
                    $caption      // caption
                );

                // Si el texto es más largo que 1024, lo enviamos también como mensaje normal
                if (mb_strlen($text) > 1024) {
                    $telegram->sendMessage($cred->chat_id, $text);
                }
            } else {
                // Sin adjunto: lo de siempre
                $telegram->sendMessage($cred->chat_id, $text);
            }

            \Log::info("Mensaje de Telegram enviado correctamente para el usuario {$user->id}");
        } catch (\Exception $e) {
            \Log::error("Error enviando mensaje de Telegram: " . $e->getMessage());
            throw $e;
        }
    }

}
