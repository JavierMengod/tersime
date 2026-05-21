<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroAlerta extends Model
{
    use HasFactory;

    protected $table = 'registros_alerta';

    protected $fillable = [
        'user_id', 'rule_id', 'rule_name',
        'dispositivo_id', 'device_name',
        'type', 'channels', 'message',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function regla()
    {
        return $this->belongsTo(Regla::class, 'rule_id');
    }

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
