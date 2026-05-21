<?php

namespace Database\Seeders;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Database\Seeder;

class DispositivoSeeder extends Seeder
{
    public function run()
    {
        $cabras   = Dispositivo::create(['etiqueta_influx' => 'cabras']);
        $camara2  = Dispositivo::create(['etiqueta_influx' => 'camara_2']);

        $javier = User::where('name', 'Javier')->first();
        $julio  = User::where('name', 'Julio')->first();

        $javier->dispositivos()->sync([
            $cabras->id  => ['nombre' => 'Cabras'],
            $camara2->id => ['nombre' => 'Cámara 2'],
        ]);

        $julio->dispositivos()->sync([
            $cabras->id  => ['nombre' => 'Cabras'],
            $camara2->id => ['nombre' => 'Cámara 2'],
        ]);
    }
}
