<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertLog extends Model
{
    protected $fillable = [
        'user_id', 'rule_id', 'rule_name',
        'dispositivo_id', 'device_name',
        'type', 'channels', 'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function channelList(): array
    {
        return $this->channels ? explode(',', $this->channels) : [];
    }
}
