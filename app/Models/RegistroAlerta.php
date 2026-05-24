<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroAlerta extends Model
{
    use HasFactory;

    protected $table = 'registros_alerta';

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'user_id', 'regla_id', 'nombre_regla',
        'dispositivo_id', 'nombre_dispositivo',
        'tipo', 'canales', 'mensaje',
    ];

    protected $casts = [
        'canales' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function regla()
    {
        return $this->belongsTo(Regla::class, 'regla_id');
    }

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function scopePorUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
