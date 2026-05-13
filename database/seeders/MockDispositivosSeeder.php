<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Dispositivo;
use App\Models\User;

class MockDispositivosSeeder extends Seeder
{
    public function run(): void
    {
        $mockDevices = [
            ['nombre' => 'Oficina Principal', 'URL' => 'oficina'],
            ['nombre' => 'Sala Servidores',   'URL' => 'servidores'],
            ['nombre' => 'Almacen',           'URL' => 'almacen'],
        ];

        $userIds = User::pluck('id');

        foreach ($mockDevices as $data) {
            // Skip if already exists (idempotent)
            if (Dispositivo::where('URL', $data['URL'])->exists()) {
                continue;
            }

            $dispositivo = Dispositivo::create($data);

            // Assign to all users
            foreach ($userIds as $userId) {
                DB::table('user_dispositivo')->insertOrIgnore([
                    'user_id'        => $userId,
                    'dispositivo_id' => $dispositivo->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }
}
