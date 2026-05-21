<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroEstadoDispositivo extends Model
{
    public $timestamps = false;

    protected $table = 'registros_estado_dispositivo';

    protected $fillable = ['user_id', 'dispositivo_id', 'habilitado', 'modificado_en'];

    protected $casts = [
        'habilitado'   => 'boolean',
        'modificado_en'=> 'datetime',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
