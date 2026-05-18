<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefactorDispositivosArchitecture extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE user_dispositivo
                SET nombre = (
                    SELECT nombre FROM dispositivos
                    WHERE dispositivos.id = user_dispositivo.dispositivo_id
                )
            ');

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

            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS ud_user_device_unique
                ON user_dispositivo (user_id, dispositivo_id)
            ');
            return;
        }

        // MySQL — usar ALTER TABLE en lugar de recrear la tabla
        DB::statement('
            UPDATE user_dispositivo ud
            INNER JOIN dispositivos d ON d.id = ud.dispositivo_id
            SET ud.nombre = d.nombre
        ');

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->string('influx_tag')->nullable()->after('id');
        });

        DB::statement('UPDATE dispositivos SET influx_tag = URL');
        DB::statement('ALTER TABLE dispositivos MODIFY influx_tag VARCHAR(255) NOT NULL');

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn('URL');
        });
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->unique('influx_tag');
        });

        try {
            Schema::table('user_dispositivo', function (Blueprint $table) {
                $table->unique(['user_id', 'dispositivo_id'], 'ud_user_device_unique');
            });
        } catch (\Throwable $e) {}
    }

    public function down()
    {
        if (DB::getDriverName() === 'sqlite') {
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
            return;
        }

        try {
            Schema::table('user_dispositivo', function (Blueprint $table) {
                $table->dropUnique('ud_user_device_unique');
            });
        } catch (\Throwable $e) {}

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropUnique(['influx_tag']);
            $table->string('nombre')->default('');
            $table->string('URL')->nullable();
        });

        DB::statement('UPDATE dispositivos SET URL = influx_tag');
        DB::statement('ALTER TABLE dispositivos MODIFY URL VARCHAR(255) NOT NULL');

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn('influx_tag');
        });
    }
}
