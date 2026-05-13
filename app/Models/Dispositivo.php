<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'URL',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'user_dispositivo');
    }

    public function rules()
    {
        return $this->belongsToMany(Rule::class, 'dispositivo_rule', 'dispositivo_id', 'rule_id');
    }

    public function informes()
    {
        return $this->belongsToMany(
            Informe::class,
            'informe_dispositivo'
        )->withTimestamps();
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
