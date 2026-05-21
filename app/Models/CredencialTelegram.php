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
        'active',
    ];

    protected $hidden = ['bot_token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
