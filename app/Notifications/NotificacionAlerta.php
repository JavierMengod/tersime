<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionAlerta extends Notification
{
    private string $tipo;
    private string $titulo;
    private string $mensaje;

    public function __construct(string $tipo, string $ruleName, string $deviceName, string $mensaje)
    {
        $this->tipo    = $tipo;
        $this->mensaje = $mensaje;
        $this->titulo  = $tipo === 'firing'
            ? "Alerta en {$deviceName} — {$ruleName}"
            : "Resuelta: {$deviceName} — {$ruleName}";
    }

    public function getTipo(): string   { return $this->tipo; }
    public function getTitulo(): string { return $this->titulo; }
    public function getMensaje(): string { return $this->mensaje; }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'    => $this->tipo,
            'titulo'  => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'     => route('alertas.historial'),
        ];
    }
}
