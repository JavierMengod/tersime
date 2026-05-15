<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNombreHabilitadoToUserDispositivoTable extends Migration
{
    public function up()
    {
        Schema::table('user_dispositivo', function (Blueprint $table) {
            $table->string('nombre')->nullable();
        });
    }

    public function down()
    {
        Schema::table('user_dispositivo', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
}
