<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Informe extends Model
{
    protected $fillable = [
        'user_id',
        'nombre',
        'nombre_archivo',
        'pdf_path',
        'periodo_from',
        'periodo_to',
        'periodicidad',
        'notificaciones',
        'correo_destino',
        'activo',
        'telegram',
        'discord',
        'correo',
        'size_bytes',
        'generated_at',
    ];

    protected $casts = [
        'notificaciones' => 'array',
        'generated_at' => 'datetime',
        'periodo_from' => 'date',
        'periodo_to' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'informe_dispositivo'
        )->withTimestamps();
    }
}
