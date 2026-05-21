<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedioNotificacion extends Model
{
    use HasFactory;

    protected $table = 'medios_notificacion';

    protected $fillable = ['canal', 'activo', 'configuracion'];
    protected $casts = [
        'activo'        => 'boolean',
        'configuracion' => 'array',
    ];
}
