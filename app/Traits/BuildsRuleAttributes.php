<?php

namespace App\Traits;

trait BuildsRuleAttributes
{
    protected function ruleFieldsFrom(array $validated): array
    {
        $methods = $validated['methods'] ?? [];
        return [
            'name'              => $validated['name'],
            'operator'          => $validated['operator'],
            'comparison_value'  => $validated['value'],
            'for_duration'      => $validated['for_duration'] * 60,
            'email_enabled'     => in_array('email',    $methods, true),
            'telegram_enabled'  => in_array('telegram', $methods, true),
            'discord_enabled'   => in_array('discord',  $methods, true),
            'template_telegram' => $validated['template_telegram'] ?? null,
            'template_email'    => $validated['template_email']    ?? null,
            'template_discord'  => $validated['template_discord']  ?? null,
            'recipient_email'   => $validated['recipient_email']   ?? null,
        ];
    }
}
