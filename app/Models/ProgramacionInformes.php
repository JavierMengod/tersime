<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramacionInformes extends Model
{
    use HasFactory;

    protected $table = 'programacion_informes';

    protected $fillable = [
        'user_id',
        'nombre',
        'periodicidad',
        'tipo_periodo',
        'valor_periodo',
        'telegram',
        'discord',
        'correo',
        'correo_destino',
        'activo',
        'last_run_at',
    ];

    protected $casts = [
        'last_run_at'   => 'datetime',
        'activo'        => 'boolean',
        'telegram'      => 'boolean',
        'discord'       => 'boolean',
        'correo'        => 'boolean',
        'valor_periodo' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'dispositivo_programacion_informe',
            'programacion_informe_id',
            'dispositivo_id'
        )->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function formatearFrecuencia(): string
    {
        $v    = (int) ($this->valor_periodo ?? 1);
        $tipo = $this->tipo_periodo ?? 'horas';

        if ($tipo === 'meses') {
            return $v . ($v === 1 ? ' mes' : ' meses');
        }
        if ($tipo === 'dias') {
            return $v . ($v === 1 ? ' día' : ' días');
        }
        return $v . ($v === 1 ? ' hora' : ' horas');
    }

    public function proximaEjecucion(): ?Carbon
    {
        if (!$this->last_run_at) {
            return null;
        }
        return $this->last_run_at->copy()->addHours($this->periodicidad);
    }
}
