<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Informe extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'nombre',
        'nombre_archivo',
        'pdf_path',
        'periodo_from',
        'periodo_to',
        'correo_destino',
        'telegram',
        'discord',
        'correo',
        'size_bytes',
        'generated_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'generated_at' => 'datetime',
        'periodo_from' => 'date',
        'periodo_to'   => 'date',
    ];

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dispositivos()
    {
        return $this->belongsToMany(
            Dispositivo::class,
            'informe_dispositivo'
        )->withTimestamps();
    }
}
