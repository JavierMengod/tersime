<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'bot_token',
        'active',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
