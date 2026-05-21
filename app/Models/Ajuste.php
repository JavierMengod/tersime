<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    protected $primaryKey = 'clave';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $fillable   = ['clave', 'valor'];

    public static function get(string $clave, $defecto = null): ?string
    {
        $registro = static::find($clave);
        return $registro ? $registro->valor : $defecto;
    }

    public static function set(string $clave, $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
