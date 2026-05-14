<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'operator',
        'time_range',
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
        'last_triggered_at' => 'datetime',
    ];

    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'dispositivo_rule', 'rule_id', 'dispositivo_id')
                    ->withPivot('last_triggered_at', 'alert_state', 'pending_since');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
