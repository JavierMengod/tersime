<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispositivoEstadoLog extends Model
{
    public $timestamps = false;

    protected $table = 'dispositivo_estado_log';

    protected $fillable = ['user_id', 'dispositivo_id', 'habilitado', 'changed_at'];

    protected $casts = [
        'habilitado'  => 'boolean',
        'changed_at'  => 'datetime',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
