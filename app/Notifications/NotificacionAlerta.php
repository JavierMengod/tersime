<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionAlerta extends Notification
{
    public string $tipo;
    public string $titulo;
    public string $mensaje;

    public function __construct(string $tipo, string $ruleName, string $deviceName, string $mensaje)
    {
        $this->tipo    = $tipo;
        $this->mensaje = $mensaje;
        $this->titulo  = $tipo === 'firing'
            ? "Alerta en {$deviceName} — {$ruleName}"
            : "Resuelta: {$deviceName} — {$ruleName}";
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'tipo'    => $this->tipo,
            'titulo'  => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'     => route('alertas.historial'),
        ];
    }
}
