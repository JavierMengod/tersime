<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionInforme extends Notification
{
    private string $titulo;
    private string $mensaje;
    private string $downloadUrl;

    public function __construct(int $informeId, string $fromDate, string $toDate)
    {
        $desde = \Carbon\Carbon::parse($fromDate)->format('d/m/Y');
        $hasta = \Carbon\Carbon::parse($toDate)->format('d/m/Y');

        $this->titulo      = 'Informe generado';
        $this->mensaje     = "Tu informe del período {$desde} al {$hasta} está listo.";
        $this->downloadUrl = route('informes.download', $informeId, false);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
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
