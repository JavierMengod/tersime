<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmtpCredential extends Model
{
    protected $fillable = [
        'user_id', 'host', 'port', 'username', 'from_address', 'password', 'encryption', 'active',
    ];

    protected $hidden = ['password'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
