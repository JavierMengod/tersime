<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RefactorDispositivosArchitecture extends Migration
{
    public function up()
    {
        // nombre ya existe en user_dispositivo (de una ejecución parcial anterior)
        // 1. Poblar nombre desde dispositivos → user_dispositivo
        DB::statement('
            UPDATE user_dispositivo
            SET nombre = (
                SELECT nombre FROM dispositivos
                WHERE dispositivos.id = user_dispositivo.dispositivo_id
            )
        ');

        // 2. Recrear tabla dispositivos: URL → influx_tag, sin columna nombre
        DB::statement('
            CREATE TABLE dispositivos_new (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                influx_tag TEXT NOT NULL UNIQUE,
                created_at DATETIME,
                updated_at DATETIME
            )
        ');

        DB::statement('
            INSERT INTO dispositivos_new (id, influx_tag, created_at, updated_at)
            SELECT id, URL, created_at, updated_at FROM dispositivos
        ');

        DB::statement('DROP TABLE dispositivos');
        DB::statement('ALTER TABLE dispositivos_new RENAME TO dispositivos');

        // 3. Unicidad en pivot (user_id, dispositivo_id)
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS ud_user_device_unique
            ON user_dispositivo (user_id, dispositivo_id)
        ');
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS ud_user_device_unique');

        DB::statement('
            CREATE TABLE dispositivos_old (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre     VARCHAR NOT NULL DEFAULT "",
                URL        VARCHAR NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )
        ');

        DB::statement('
            INSERT INTO dispositivos_old (id, URL, created_at, updated_at)
            SELECT id, influx_tag, created_at, updated_at FROM dispositivos
        ');

        DB::statement('DROP TABLE dispositivos');
        DB::statement('ALTER TABLE dispositivos_old RENAME TO dispositivos');
    }
}
