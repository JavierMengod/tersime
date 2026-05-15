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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'language',
        'timezone',
        'theme',
        'admin',
        'enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'admin'   => 'boolean',
        'enabled' => 'boolean',
    ];

    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'user_dispositivo')
                    ->withPivot('nombre', 'habilitado')
                    ->withTimestamps();
    }

    public function smtpCredential()
    {
        return $this->hasOne(SmtpCredential::class);
    }

    public function telegramCredential()
    {
        return $this->hasOne(TelegramCredential::class);
    }

    public function rules()
    {
        return $this->hasMany(Rule::class);
    }

    public function discordCredential()
    {
        return $this->hasOne(DiscordCredential::class);
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
