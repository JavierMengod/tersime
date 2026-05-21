<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionAlerta extends Notification
{
    private string $tipo;
    private string $nombreDispositivo;
    private string $mensaje;
    private array  $canales;
    private string $titulo;

    public function __construct(string $tipo, string $nombreRegla, string $nombreDispositivo, string $mensaje, array $canales = [])
    {
        $this->tipo              = $tipo;
        $this->nombreDispositivo = $nombreDispositivo;
        $this->mensaje           = $mensaje;
        $this->canales           = $canales;
        $this->titulo            = $tipo === 'firing'
            ? "Alerta en {$nombreDispositivo} — {$nombreRegla}"
            : "Resuelta: {$nombreDispositivo} — {$nombreRegla}";
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
            'nombre_dispositivo' => $this->nombreDispositivo,
            'canales'            => $this->canales,
            'url'                => route('alertas.historial'),
        ];
    }
}
