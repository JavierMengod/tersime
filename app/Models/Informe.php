<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Informe extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'nombre',
        'nombre_archivo',
        'pdf_path',
        'periodo_from',
        'periodo_to',
        'correo_destino',
        'telegram',
        'discord',
        'correo',
        'tamano_bytes',
        'generado_en',
        'estado',
        'mensaje_error',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'generado_en'  => 'datetime',
        'periodo_from' => 'date',
        'periodo_to'   => 'date',
    ];

    public function estaPendiente(): bool    { return $this->estado === 'pending'; }
    public function estaProcesando(): bool   { return $this->estado === 'processing'; }
    public function estaCompletado(): bool   { return $this->estado === 'completed'; }
    public function estaFallido(): bool      { return $this->estado === 'failed'; }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'informe_dispositivo'
        )->withTimestamps();
    }
}
