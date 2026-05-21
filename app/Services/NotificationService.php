<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use TelegramBot\Api\BotApi;

class NotificationService
{
    public function sendEmail(string $texto, User $usuario, string $correoDestinatario, ?string $direccionRemitente = null): void
    {
        $remitente = $this->prepararSmtp($usuario, $direccionRemitente);

        Mail::raw($texto, function ($message) use ($correoDestinatario, $remitente) {
            $message->to($correoDestinatario)
                    ->from($remitente, 'Tersime')
                    ->subject('📩 Notificación del sistema');
        });

        Log::info("Correo enviado a {$correoDestinatario} para usuario {$usuario->id}");
    }

    public function sendTelegram(string $texto, User $usuario): void
    {
        $credencial = $this->requerirCredencialActiva($usuario->credencialTelegram, 'Telegram');

        $bot = new BotApi(decrypt($credencial->bot_token));
        $bot->sendMessage($credencial->chat_id, $texto);

        Log::info("Telegram enviado para usuario {$usuario->id}");
    }

    public function sendDiscord(string $texto, User $usuario): void
    {
        $credencial = $this->requerirCredencialActiva($usuario->credencialDiscord, 'Discord');

        $respuesta = Http::post($credencial->webhook_url, ['content' => $texto]);

        if ($respuesta->failed()) {
            throw new \RuntimeException("Discord rechazó el mensaje: HTTP " . $respuesta->status());
        }

        Log::info("Discord enviado para usuario {$usuario->id}");
    }

    public function sendEmailWithAttachment(string $texto, User $usuario, string $correoDestinatario, ?string $rutaPdf = null, ?string $direccionRemitente = null): void
    {
        $remitente = $this->prepararSmtp($usuario, $direccionRemitente);

        Mail::send([], [], function ($message) use ($correoDestinatario, $remitente, $texto, $rutaPdf) {
            $message->to($correoDestinatario)
                    ->from($remitente, 'Tersime')
                    ->subject('📩 Notificación del sistema')
                    ->setBody($texto, 'text/plain');

            if ($rutaPdf && file_exists($rutaPdf)) {
                $message->attach($rutaPdf, ['mime' => 'application/pdf']);
            }
        });

        Log::info("Correo con adjunto enviado a {$correoDestinatario} para usuario {$usuario->id}");
    }

    public function sendTelegramWithAttachment(string $texto, User $usuario, ?string $rutaPdf = null): void
    {
        $credencial = $this->requerirCredencialActiva($usuario->credencialTelegram, 'Telegram');

        $bot = new BotApi(decrypt($credencial->bot_token));

        if ($rutaPdf) {
            $rutaReal = realpath($rutaPdf);
            if ($rutaReal === false || !is_file($rutaReal) || !is_readable($rutaReal)) {
                throw new \InvalidArgumentException("La ruta del PDF no existe o no es legible: {$rutaPdf}");
            }

            $leyenda = mb_substr($texto, 0, 1024);
            $archivo = new \CURLFile($rutaReal, 'application/pdf', basename($rutaReal));
            $bot->sendDocument($credencial->chat_id, $archivo, $leyenda);

            if (mb_strlen($texto) > 1024) {
                $bot->sendMessage($credencial->chat_id, $texto);
            }
        } else {
            $bot->sendMessage($credencial->chat_id, $texto);
        }

        Log::info("Telegram con adjunto enviado para usuario {$usuario->id}");
    }

    public function sendDiscordWithFile(string $texto, string $rutaArchivo, User $usuario): void
    {
        $credencial = $this->requerirCredencialActiva($usuario->credencialDiscord, 'Discord');

        if (!file_exists($rutaArchivo)) {
            throw new \RuntimeException("El archivo no existe: {$rutaArchivo}");
        }

        $respuesta = Http::attach('file', file_get_contents($rutaArchivo), basename($rutaArchivo))
            ->post($credencial->webhook_url, ['payload_json' => json_encode(['content' => $texto])]);

        if ($respuesta->failed()) {
            throw new \RuntimeException("Error enviando archivo a Discord: HTTP " . $respuesta->status());
        }

        Log::info("Discord con archivo enviado para usuario {$usuario->id}");
    }

    private function requerirCredencialActiva($credencial, string $canal): object
    {
        if (!$credencial || !$credencial->activo) {
            throw new \RuntimeException("El usuario no tiene {$canal} configurado o activo.");
        }
        return $credencial;
    }

    private function prepararSmtp(User $usuario, ?string $direccionRemitente): string
    {
        $credencial = $this->requerirCredencialActiva($usuario->credencialSmtp, 'SMTP');

        $remitente = $direccionRemitente ?? $credencial->from_address ?? $credencial->username;

        if (empty($remitente)) {
            throw new \RuntimeException("No hay dirección de remitente válida.");
        }

        $this->configurarSmtp($credencial, decrypt($credencial->password), $remitente);

        return $remitente;
    }

    private function configurarSmtp($credencial, string $contrasena, string $remitente): void
    {
        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $credencial->host,
            'mail.mailers.smtp.port'       => $credencial->port,
            'mail.mailers.smtp.encryption' => $credencial->encryption,
            'mail.mailers.smtp.username'   => $credencial->username,
            'mail.mailers.smtp.password'   => $contrasena,
            'mail.from.address'            => $remitente,
            'mail.from.name'               => 'Tersime',
        ]);
        
        app('mail.manager')->purge('smtp');
    }
}
