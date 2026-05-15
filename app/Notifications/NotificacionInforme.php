<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionInforme extends Notification
{
    public string $titulo;
    public string $mensaje;
    public string $downloadUrl;

    public function __construct(string $filename, string $fromDate, string $toDate)
    {
        $this->titulo      = 'Informe generado';
        $this->mensaje     = "Tu informe del período {$fromDate} al {$toDate} está listo.";
        $this->downloadUrl = route('informes.demanda.download', ['filename' => $filename]);
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'tipo'   => 'informe',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'    => $this->downloadUrl,
            'icono'  => 'informe',
        ];
    }
}
