<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'nombre_archivo' => $this->nombre_archivo,
            'tipo'           => $this->tipo,
            'from'           => $this->periodo_from,
            'to'             => $this->periodo_to,
            'generado_en'    => $this->generado_en,
            'tamano_bytes'   => $this->tamano_bytes,
            'dispositivos'   => $this->whenLoaded('dispositivos', fn() =>
                $this->dispositivos->map(fn($d) => [
                    'id'        => $d->id,
                    'etiqueta_influx' => $d->etiqueta_influx,
                ])
            ),
        ];
    }
}
