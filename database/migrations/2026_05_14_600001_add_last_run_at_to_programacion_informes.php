<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastRunAtToProgramacionInformes extends Migration
{
    public function up()
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->timestamp('last_run_at')->nullable()->after('activo');
        });
    }

    public function down()
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->dropColumn('last_run_at');
        });
    }
}
