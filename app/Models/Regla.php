<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regla extends Model
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
        return $this->belongsToMany(Dispositivo::class, 'dispositivo_regla', 'rule_id', 'dispositivo_id')
                    ->withPivot('last_triggered_at', 'alert_state', 'pending_since')
                    ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAlertStateAttribute(): string
    {
        if (!$this->relationLoaded('dispositivos')) {
            return 'ok';
        }

        $states = $this->dispositivos->pluck('pivot.alert_state')->toArray();

        if (in_array('firing',  $states)) {
            return 'firing';
        }
        if (in_array('pending', $states)) {
            return 'pending';
        }

        return 'ok';
    }

    public static function userHasReachedLimit(int $userId): bool
    {
        return static::where('user_id', $userId)->count() >= 50;
    }

    public function getEnabledChannelsAttribute(): array
    {
        $channels = [];
        if ($this->telegram_enabled) $channels[] = 'telegram';
        if ($this->email_enabled)    $channels[] = 'email';
        if ($this->discord_enabled)  $channels[] = 'discord';
        return $channels;
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
