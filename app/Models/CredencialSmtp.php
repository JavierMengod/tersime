<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CredencialSmtp extends Model
{
    protected $table = 'credenciales_smtp';

    protected $fillable = [
        'user_id', 'host', 'puerto', 'usuario', 'direccion_remitente', 'contrasena', 'cifrado', 'activo',
    ];

    protected $hidden = ['contrasena'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
