<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NotificacionInforme extends Notification
{
    private string $titulo;
    private string $mensaje;
    private string $downloadUrl;

    public function __construct(
        int    $idInforme,
        string $fechaDesde,
        string $fechaHasta,
        private string $nombreArchivo = '',
        private string $subtipo       = 'demanda',
    ) {
        $desde = \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y');
        $hasta = \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y');

        $this->titulo      = 'Informe generado';
        $this->mensaje     = "Tu informe del período {$desde} al {$hasta} está listo.";
        $this->downloadUrl = route('informes.download', $idInforme, false);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'           => 'informe',
            'icono'          => 'informe',
            'subtipo'        => $this->subtipo,
            'titulo'         => $this->titulo,
            'mensaje'        => $this->mensaje,
            'nombre_archivo' => $this->nombreArchivo,
            'url'            => $this->downloadUrl,
        ];
    }
}
