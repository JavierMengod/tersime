<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPeriodoFieldsToProgramacionInformes extends Migration
{
    public function up()
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->string('tipo_periodo', 10)->default('horas')->after('periodicidad');
            $table->unsignedInteger('valor_periodo')->default(1)->after('tipo_periodo');
        });

        // Poblar los nuevos campos deduciendo desde periodicidad (horas) en registros existentes
        DB::table('programacion_informes')->get()->each(function ($p) {
            $horas = (int) $p->periodicidad;
            if ($horas >= 720 && $horas % 720 === 0) {
                $tipo  = 'meses';
                $valor = $horas / 720;
            } elseif ($horas >= 24 && $horas % 24 === 0) {
                $tipo  = 'dias';
                $valor = $horas / 24;
            } else {
                $tipo  = 'horas';
                $valor = $horas;
            }
            DB::table('programacion_informes')->where('id', $p->id)->update([
                'tipo_periodo'  => $tipo,
                'valor_periodo' => $valor,
            ]);
        });
    }

    public function down()
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->dropColumn(['tipo_periodo', 'valor_periodo']);
        });
    }
}
