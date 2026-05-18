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
            'generated_at'   => $this->generated_at,
            'size_bytes'     => $this->size_bytes,
            'dispositivos'   => $this->whenLoaded('dispositivos', fn() =>
                $this->dispositivos->map(fn($d) => [
                    'id'        => $d->id,
                    'influx_tag' => $d->influx_tag,
                ])
            ),
        ];
    }
}
