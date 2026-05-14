<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Dispositivo extends Model
{
    use HasFactory;

    protected $fillable = ['influx_tag'];

    /**
     * Devuelve el nombre amigable del usuario para este dispositivo.
     * Cuando el modelo viene cargado a través de la relación del usuario (withPivot),
     * lee del pivot. En otro contexto (reglas, etc.) hace un lookup puntual.
     */
    public function getNombreAttribute(): string
    {
        if (isset($this->pivot) && isset($this->pivot->nombre)) {
            return $this->pivot->nombre;
        }

        if (auth()->check()) {
            $nombre = DB::table('user_dispositivo')
                ->where('user_id', auth()->id())
                ->where('dispositivo_id', $this->id)
                ->value('nombre');

            if ($nombre !== null) {
                return $nombre;
            }
        }

        return $this->influx_tag;
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'user_dispositivo')
                    ->withPivot('nombre')
                    ->withTimestamps();
    }

    public function rules()
    {
        return $this->belongsToMany(Rule::class, 'dispositivo_rule', 'dispositivo_id', 'rule_id');
    }

    public function informes()
    {
        return $this->belongsToMany(Informe::class, 'informe_dispositivo')->withTimestamps();
    }

    public function programaciones()
    {
        return $this->belongsToMany(
            ProgramacionInformes::class,
            'dispositivo_programacion_informe',
            'dispositivo_id',
            'programacion_informe_id'
        )->withTimestamps();
    }
}
