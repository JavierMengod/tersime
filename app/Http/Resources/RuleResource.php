<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RuleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'operator'          => $this->operator,
            'value'             => $this->comparison_value,
            'for_duration'      => $this->for_duration,
            'is_active'         => $this->is_active,
            'email_enabled'     => $this->email_enabled,
            'telegram_enabled'  => $this->telegram_enabled,
            'discord_enabled'   => $this->discord_enabled,
            'recipient_email'   => $this->recipient_email,
            'last_triggered_at' => $this->last_triggered_at,
            'devices'           => $this->whenLoaded('dispositivos', fn() =>
                $this->dispositivos->map(fn($d) => [
                    'id'     => $d->id,
                    'nombre' => $d->nombre,
                ])
            ),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
