<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordCredential extends Model
{
    protected $fillable = ['user_id','webhook_url','active'];
    protected $casts = ['active'=>'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
