<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropDispositivoInformeTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('dispositivo_informe');
    }

    public function down()
    {
        // Tabla obsoleta — no se restaura
    }
}
