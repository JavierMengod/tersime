<?php

namespace Database\Seeders;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MockDispositivosSeeder extends Seeder
{
    public function run(): void
    {
        $dispositivos = [
            ['etiqueta_influx' => 'oficina',    'nombre' => 'Oficina Principal'],
            ['etiqueta_influx' => 'servidores', 'nombre' => 'Sala Servidores'],
            ['etiqueta_influx' => 'almacen',    'nombre' => 'Almacén'],
        ];

        $usuarios = User::pluck('id');

        foreach ($dispositivos as $datos) {
            if (Dispositivo::where('etiqueta_influx', $datos['etiqueta_influx'])->exists()) {
                continue;
            }

            $dispositivo = Dispositivo::create(['etiqueta_influx' => $datos['etiqueta_influx']]);

            foreach ($usuarios as $userId) {
                DB::table('user_dispositivo')->insertOrIgnore([
                    'user_id'        => $userId,
                    'dispositivo_id' => $dispositivo->id,
                    'nombre'         => $datos['nombre'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }
}
