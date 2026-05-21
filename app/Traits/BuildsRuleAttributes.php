<?php

namespace App\Traits;

trait BuildsRuleAttributes
{
    protected function camposReglaDesde(array $validated): array
    {
        $metodos = $validated['methods'] ?? [];
        return [
            'nombre'             => $validated['name'],
            'operador'           => $validated['operator'],
            'valor_comparacion'  => $validated['value'],
            'duracion'           => $validated['for_duration'] * 60,
            'correo_activo'      => in_array('email',    $metodos, true),
            'telegram_activo'    => in_array('telegram', $metodos, true),
            'discord_activo'     => in_array('discord',  $metodos, true),
            'plantilla_telegram' => $validated['template_telegram'] ?? null,
            'plantilla_correo'   => $validated['template_email']    ?? null,
            'plantilla_discord'  => $validated['template_discord']  ?? null,
            'correo_destinatario' => $validated['recipient_email']  ?? null,
        ];
    }
}
