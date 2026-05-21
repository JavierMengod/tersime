<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialDiscord extends Model
{
    protected $table = 'credenciales_discord';

    protected $fillable = ['user_id','webhook_url','active'];
    protected $casts = ['active'=>'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
