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
                              ->where('active', true)
                              ->first();

        if (! $cred) {
            throw new \Exception("No hay credenciales SMTP activas para el usuario {$userId}.");
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $cred->host,
            'mail.mailers.smtp.port'       => $cred->port,
            'mail.mailers.smtp.encryption' => $cred->encryption,
            'mail.mailers.smtp.username'   => $cred->username,
            'mail.mailers.smtp.password'   => decrypt($cred->password),
            'mail.from.address'            => $cred->from_address ?? config('mail.from.address'),
            'mail.from.name'               => $cred->from_name    ?? config('mail.from.name'),
        ]);

        Mail::mailer('smtp')->to($to)->send($mailable);
    }
}
