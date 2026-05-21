<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CredencialTelegram extends Model
{
    use HasFactory;

    protected $table = 'credenciales_telegram';

    protected $fillable = [
        'chat_id',
        'bot_token',
        'activo',
    ];

    protected $hidden = ['bot_token'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
