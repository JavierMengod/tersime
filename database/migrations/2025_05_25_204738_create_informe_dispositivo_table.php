<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInformeDispositivoTable extends Migration
{
    public function up()
    {
        Schema::create('informe_dispositivo', function (Blueprint $table) {
            $table->id();

            $table->foreignId('informe_id')
                  ->constrained('informes')
                  ->onDelete('cascade');

            $table->foreignId('dispositivo_id')
                  ->constrained('dispositivos')
                  ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('informe_dispositivo');
    }
}
