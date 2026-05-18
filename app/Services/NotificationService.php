<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use TelegramBot\Api\BotApi;

class NotificationService
{
    public function sendEmail(string $text, User $user, string $recipientEmail, ?string $fromAddress = null): void
    {
        $from = $this->prepareSmtp($user, $fromAddress);

        Mail::raw($text, function ($message) use ($recipientEmail, $from) {
            $message->to($recipientEmail)
                    ->from($from, 'Tersime')
                    ->subject('📩 Notificación del sistema');
        });

        Log::info("Correo enviado a {$recipientEmail} para usuario {$user->id}");
    }

    public function sendTelegram(string $text, User $user): void
    {
        $cred = $this->requireActiveCredential($user->telegramCredential, 'Telegram');

        $telegram = new BotApi(decrypt($cred->bot_token));
        $telegram->sendMessage($cred->chat_id, $text);

        Log::info("Telegram enviado para usuario {$user->id}");
    }

    public function sendDiscord(string $text, User $user): void
    {
        $cred = $this->requireActiveCredential($user->discordCredential, 'Discord');

        $response = Http::post($cred->webhook_url, ['content' => $text]);

        if ($response->failed()) {
            throw new \RuntimeException("Discord rechazó el mensaje: HTTP " . $response->status());
        }

        Log::info("Discord enviado para usuario {$user->id}");
    }

    public function sendEmailWithAttachment(string $text, User $user, string $recipientEmail, ?string $pdfPath = null, ?string $fromAddress = null): void
    {
        $from = $this->prepareSmtp($user, $fromAddress);

        Mail::send([], [], function ($message) use ($recipientEmail, $from, $text, $pdfPath) {
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

    public function sendTelegramWithAttachment(string $text, User $user, ?string $pdfPath = null): void
    {
        $cred = $this->requireActiveCredential($user->telegramCredential, 'Telegram');

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

    public function sendDiscordWithFile(string $message, string $filePath, User $user): void
    {
        $cred = $this->requireActiveCredential($user->discordCredential, 'Discord');

        if (!file_exists($filePath)) {
            throw new \RuntimeException("El archivo no existe: {$filePath}");
        }

        $response = Http::attach('file', file_get_contents($filePath), basename($filePath))
            ->post($cred->webhook_url, ['content' => $message]);

        if ($response->failed()) {
            throw new \RuntimeException("Error enviando archivo a Discord: HTTP " . $response->status());
        }

        Log::info("Discord con archivo enviado para usuario {$user->id}");
    }

    private function requireActiveCredential($cred, string $channel): object
    {
        if (!$cred || !$cred->active) {
            throw new \RuntimeException("El usuario no tiene {$channel} configurado o activo.");
        }
        return $cred;
    }

    private function prepareSmtp(User $user, ?string $fromAddress): string
    {
        $cred = $this->requireActiveCredential($user->smtpCredential, 'SMTP');

        $from = $fromAddress ?? $cred->from_address ?? $cred->username;

        if (empty($from)) {
            throw new \RuntimeException("No hay dirección de remitente válida.");
        }

        $this->configureSMTP($cred, decrypt($cred->password), $from);

        return $from;
    }

    private function configureSMTP($cred, string $rawPassword, string $from): void
    {
        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $cred->host,
            'mail.mailers.smtp.port'       => $cred->port,
            'mail.mailers.smtp.encryption' => $cred->encryption,
            'mail.mailers.smtp.username'   => $cred->username,
            'mail.mailers.smtp.password'   => $rawPassword,
            'mail.from.address'            => $from,
            'mail.from.name'               => 'Tersime',
        ]);
        // Force Laravel to rebuild the SMTP mailer with the new config.
        // Without this, a cached mailer from a previous user's config would be reused.
        app('mail.manager')->purge('smtp');
    }
}
