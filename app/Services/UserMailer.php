<?php

namespace App\Services;

use App\Models\CredencialSmtp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

class UserMailer
{
    public function sendUsingUser(int $userId, string $to, Mailable $mailable): void
    {
        $cred = CredencialSmtp::where('user_id', $userId)
                              ->where('activo', true)
                              ->first();

        if (! $cred) {
            throw new \Exception("No hay credenciales SMTP activas para el usuario {$userId}.");
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $cred->host,
            'mail.mailers.smtp.port'       => $cred->puerto,
            'mail.mailers.smtp.encryption' => $cred->cifrado,
            'mail.mailers.smtp.username'   => $cred->usuario,
            'mail.mailers.smtp.password'   => decrypt($cred->contrasena),
            'mail.from.address'            => $cred->direccion_remitente ?? config('mail.from.address'),
            'mail.from.name'               => config('mail.from.name'),
        ]);

        Mail::mailer('smtp')->to($to)->send($mailable);
    }
}
