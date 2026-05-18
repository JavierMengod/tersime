<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropPeriodicidadFromProgramacionInformes extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE programacion_informes_new (
                id         INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id    INTEGER NOT NULL,
                nombre     VARCHAR NOT NULL,
                telegram   TINYINT(1) NOT NULL DEFAULT 0,
                discord    TINYINT(1) NOT NULL DEFAULT 0,
                correo     TINYINT(1) NOT NULL DEFAULT 0,
                correo_destino VARCHAR NULL,
                activo     TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                last_run_at DATETIME NULL,
                tipo_periodo VARCHAR NOT NULL DEFAULT \'horas\',
                valor_periodo INTEGER NOT NULL DEFAULT 1
            )');

            DB::statement('INSERT INTO programacion_informes_new
                SELECT id, user_id, nombre, telegram, discord, correo, correo_destino,
                       activo, created_at, updated_at, last_run_at, tipo_periodo, valor_periodo
                FROM programacion_informes');

            DB::statement('DROP TABLE programacion_informes');
            DB::statement('ALTER TABLE programacion_informes_new RENAME TO programacion_informes');
            return;
        }

        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->dropColumn('periodicidad');
        });
    }

    public function down()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE programacion_informes ADD COLUMN periodicidad INTEGER NOT NULL DEFAULT 24');
            return;
        }

        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->integer('periodicidad')->default(24);
        });
    }
}
