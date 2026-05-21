<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionAlerta extends Notification
{
    private string $titulo;

    public function __construct(
        private string $tipo,
        private string $ruleName,
        private string $deviceName,
        private string $mensaje,
        private array  $canales = [],
    ) {
        $this->titulo = $tipo === 'firing'
            ? "Alerta en {$deviceName} — {$ruleName}"
            : "Resuelta: {$deviceName} — {$ruleName}";
    }

    public function getTipo(): string { return $this->tipo; }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'               => $this->tipo,
            'icono'              => $this->tipo,
            'titulo'             => $this->titulo,
            'mensaje'            => $this->mensaje,
            'nombre_dispositivo' => $this->deviceName,
            'canales'            => $this->canales,
            'url'                => route('alertas.historial'),
        ];
    }
}
