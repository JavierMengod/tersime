<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SolicitudResetPassword extends Notification
{
    public function __construct(private string $nombreUsuario, private string $ip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'    => 'reset_password',
            'icono'   => 'reset_password',
            'titulo'  => "Solicitud de contraseña: {$this->nombreUsuario}",
            'mensaje' => "El usuario '{$this->nombreUsuario}' ha solicitado restablecer su contraseña (IP: {$this->ip}). Ve a Usuarios para cambiarla.",
            'url'     => route('usuarios.index'),
        ];
    }
}
