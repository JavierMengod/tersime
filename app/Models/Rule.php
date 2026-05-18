<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'user_id',
        'operator',
        'for_duration',
        'comparison_value',
        'is_active',
        'last_triggered_at',
        'email_enabled',
        'telegram_enabled',
        'discord_enabled',
        'recipient_email',
        'template_telegram',
        'template_email',
        'template_discord',
    ];

    protected $casts = [
        'last_triggered_at'  => 'datetime',
        'for_duration'       => 'integer',
        'is_active'          => 'boolean',
        'email_enabled'      => 'boolean',
        'telegram_enabled'   => 'boolean',
        'discord_enabled'    => 'boolean',
        'comparison_value'   => 'float',
    ];

    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'dispositivo_rule', 'rule_id', 'dispositivo_id')
                    ->withPivot('last_triggered_at', 'alert_state', 'pending_since')
                    ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAlertStateAttribute(): string
    {
        if (!$this->relationLoaded('dispositivos')) return 'ok';
        $states = $this->dispositivos->pluck('pivot.alert_state')->toArray();
        if (in_array('firing',  $states)) return 'firing';
        if (in_array('pending', $states)) return 'pending';
        return 'ok';
    }

    public function getChannelBadgesAttribute(): array
    {
        $badges = [];
        if ($this->telegram_enabled) $badges[] = ['icon' => 'fab fa-telegram', 'color' => 'text-info',     'label' => 'Telegram'];
        if ($this->email_enabled)    $badges[] = ['icon' => 'fas fa-envelope',  'color' => 'text-warning',  'label' => __('Correo')];
        if ($this->discord_enabled)  $badges[] = ['icon' => 'fab fa-discord',   'color' => 'text-secondary','label' => 'Discord'];
        return $badges;
    }

    public function getOperatorLabelAttribute(): string
    {
        $labels = [
            '>'  => 'mayor que',        '<'  => 'menor que',
            '>=' => 'mayor o igual que', '<=' => 'menor o igual que',
            '==' => 'igual a',           '!=' => 'distinto de',
        ];
        return $labels[$this->operator] ?? $this->operator;
    }
}
