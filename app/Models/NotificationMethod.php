<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMethod extends Model
{
    use HasFactory;

    protected $fillable = ['channel', 'active', 'config'];
    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];

}
