<?php

namespace App\Models;

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
        'telegram',
        'discord',
        'correo',
        'correo_destino',
        'activo',
    ];

    /**
     * Relación con el usuario propietario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación muchos a muchos con dispositivos
     */
    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'dispositivo_programacion_informe',
            'programacion_informe_id',
            'dispositivo_id'
        )->withTimestamps();
    }

    /**
     * Opcional: para acceder fácilmente al estado activo/inactivo
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
