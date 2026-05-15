<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionAlerta extends Notification
{
    public string $tipo;
    public string $titulo;
    public string $mensaje;
    public string $icono;

    public function __construct(string $tipo, string $ruleName, string $deviceName, string $mensaje)
    {
        $this->tipo    = $tipo; // 'firing' | 'resolution'
        $this->mensaje = $mensaje;

        if ($tipo === 'firing') {
            $this->titulo = "Alerta disparada — {$ruleName}";
            $this->icono  = 'firing';
        } else {
            $this->titulo = "Alerta resuelta — {$ruleName}";
            $this->icono  = 'resolution';
        }
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'tipo'   => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'    => route('alertas.historial'),
            'icono'  => $this->icono,
        ];
    }
}
