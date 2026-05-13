<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $fillable = [
        'name',
        'device_id',
        'user_id',
        'operator',
        'time_range',
        'comparison_value',
        'is_active',
        'email_enabled',
        'telegram_enabled',
        'discord_enabled',
        'recipient_email',
        'template_telegram',
        'template_email',
        'template_discord',
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
