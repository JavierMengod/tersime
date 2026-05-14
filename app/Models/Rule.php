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

    // Relación: una regla pertenece a un dispositivo
    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'dispositivo_rule', 'rule_id', 'dispositivo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
