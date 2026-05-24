<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'idioma',
        'zona_horaria',
        'tema',
        'coste_kwh',
        'administrador',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'administrador' => 'boolean',
        'activo'        => 'boolean',
    ];

    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'user_dispositivo')
                    ->withPivot('nombre', 'habilitado')
                    ->withTimestamps();
    }

    public function credencialSmtp()
    {
        return $this->hasOne(CredencialSmtp::class);
    }

    public function credencialTelegram()
    {
        return $this->hasOne(CredencialTelegram::class);
    }

    public function reglas()
    {
        return $this->hasMany(Regla::class);
    }

    public function credencialDiscord()
    {
        return $this->hasOne(CredencialDiscord::class);
    }

    public function informes()
    {
        return $this->hasMany(Informe::class);
    }

    public function programacionInformes()
    {
        return $this->hasMany(ProgramacionInformes::class);
    }
}
