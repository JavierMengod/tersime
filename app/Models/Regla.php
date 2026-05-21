<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regla extends Model
{
    use HasFactory;
    protected $fillable = [
        'nombre',
        'user_id',
        'operador',
        'duracion',
        'valor_comparacion',
        'activo',
        'ultimo_disparo_en',
        'correo_activo',
        'telegram_activo',
        'discord_activo',
        'correo_destinatario',
        'plantilla_telegram',
        'plantilla_correo',
        'plantilla_discord',
    ];

    protected $casts = [
        'ultimo_disparo_en' => 'datetime',
        'duracion'          => 'integer',
        'activo'            => 'boolean',
        'correo_activo'     => 'boolean',
        'telegram_activo'   => 'boolean',
        'discord_activo'    => 'boolean',
        'valor_comparacion' => 'float',
    ];

    public function dispositivos()
    {
        return $this->belongsToMany(Dispositivo::class, 'dispositivo_regla', 'regla_id', 'dispositivo_id')
                    ->withPivot('ultimo_disparo_en', 'alert_state', 'pending_since')
                    ->withTimestamps();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEstadoAlertaAttribute(): string
    {
        if (!$this->relationLoaded('dispositivos')) {
            return 'ok';
        }

        $estados = $this->dispositivos->pluck('pivot.alert_state')->toArray();

        if (in_array('firing',  $estados)) {
            return 'firing';
        }
        if (in_array('pending', $estados)) {
            return 'pending';
        }

        return 'ok';
    }

    public static function limiteAlcanzado(int $userId): bool
    {
        return static::where('user_id', $userId)->count() >= 50;
    }

    public function getCanalesActivosAttribute(): array
    {
        $canales = [];
        if ($this->telegram_activo) $canales[] = 'telegram';
        if ($this->correo_activo)   $canales[] = 'email';
        if ($this->discord_activo)  $canales[] = 'discord';
        return $canales;
    }

    public function getEtiquetaOperadorAttribute(): string
    {
        $etiquetas = [
            '>'  => 'mayor que',        '<'  => 'menor que',
            '>=' => 'mayor o igual que', '<=' => 'menor o igual que',
            '==' => 'igual a',           '!=' => 'distinto de',
        ];
        return $etiquetas[$this->operador] ?? $this->operador;
    }
}
