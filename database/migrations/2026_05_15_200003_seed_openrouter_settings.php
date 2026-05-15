<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedOpenrouterSettings extends Migration
{
    public function up()
    {
        $defaults = [
            'openrouter_model'   => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            // La API key se omite del seed: se configura desde el panel de Conexiones.
            // Sí se puede pre-cargar desde .env si existe.
            'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
        ];

        foreach ($defaults as $key => $value) {
            if ($value !== '') {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
            }
        }
    }

    public function down()
    {
        DB::table('settings')->whereIn('key', ['openrouter_model', 'openrouter_api_key'])->delete();
    }
}
