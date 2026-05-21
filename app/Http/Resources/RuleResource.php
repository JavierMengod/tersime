<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RuleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'nombre'             => $this->nombre,
            'operador'           => $this->operador,
            'valor_comparacion'  => $this->valor_comparacion,
            'duracion'           => $this->duracion,
            'activo'             => $this->activo,
            'correo_activo'      => $this->correo_activo,
            'telegram_activo'    => $this->telegram_activo,
            'discord_activo'     => $this->discord_activo,
            'correo_destinatario' => $this->correo_destinatario,
            'ultimo_disparo_en'  => $this->ultimo_disparo_en,
            'devices'            => $this->whenLoaded('dispositivos', fn() =>
                $this->dispositivos->map(fn($d) => [
                    'id'     => $d->id,
                    'nombre' => $d->nombre,
                ])
            ),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
