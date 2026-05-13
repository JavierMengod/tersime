<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformeRegistro extends Model
{
    protected $fillable = [
        'informe_id','user_id','nombre_archivo','pdf_path','periodo_from','periodo_to','dispositivos','notificaciones','size_bytes','generated_at'
    ];

    protected $casts = [
        'dispositivos' => 'array',
        'notificaciones' => 'array',
        'generated_at' => 'datetime',
        'periodo_from' => 'date',
        'periodo_to' => 'date',
    ];

    public function informe()
    {
        return $this->belongsTo(Informe::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
