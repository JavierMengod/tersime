<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    protected $fillable = ['user_id', 'canal', 'contenido'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
