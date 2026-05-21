<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialDiscord extends Model
{
    protected $table = 'credenciales_discord';

    protected $fillable = ['user_id','webhook_url','activo'];
    protected $casts = ['activo'=>'boolean'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
