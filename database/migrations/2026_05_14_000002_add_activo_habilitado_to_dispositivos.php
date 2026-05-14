<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddActivoHabilitadoToDispositivos extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE dispositivos ADD COLUMN activo INTEGER NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE user_dispositivo ADD COLUMN habilitado INTEGER NOT NULL DEFAULT 1');
    }

    public function down()
    {
        // SQLite 3.34 no soporta DROP COLUMN — recreamos las tablas sin esas columnas
        DB::statement('CREATE TABLE dispositivos_tmp AS SELECT id, influx_tag, created_at, updated_at FROM dispositivos');
        DB::statement('DROP TABLE dispositivos');
        DB::statement('ALTER TABLE dispositivos_tmp RENAME TO dispositivos');

        DB::statement('CREATE TABLE user_dispositivo_tmp AS SELECT id, user_id, dispositivo_id, nombre, created_at, updated_at FROM user_dispositivo');
        DB::statement('DROP TABLE user_dispositivo');
        DB::statement('ALTER TABLE user_dispositivo_tmp RENAME TO user_dispositivo');
    }
}
