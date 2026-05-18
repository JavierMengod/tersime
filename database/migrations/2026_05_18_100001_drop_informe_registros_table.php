<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropInformeRegistrosTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('informe_registros');
    }

    public function down()
    {
        // Tabla huérfana — no se restaura
    }
}
