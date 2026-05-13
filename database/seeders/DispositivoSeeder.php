<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dispositivo;
use App\Models\User;

class DispositivoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear dispositivos
        $dispositivos = Dispositivo::insert([
            ['nombre' => 'Cabras', 'URL' => 'cabras'],
            ['nombre' => 'camara_2', 'URL' => 'camara_2'],
        ]);

        // Obtener usuarios recién creados (Javier y Julio)
        $usuarioJavier = User::where('name', 'Javier')->first();
        $usuarioJulio = User::where('name', 'Julio')->first();

        // Asignar dispositivos específicos a cada usuario
        $usuarioJavier->dispositivos()->sync([1,2]); // Dispositivo1 (id=1)
        $usuarioJulio->dispositivos()->sync([1,2]);
    }
}
