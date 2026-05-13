<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispositivoInformeTable extends Migration
{
    public function up()
    {
        Schema::create('dispositivo_informe', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispositivo_id')->index();
            $table->unsignedBigInteger('informe_id')->index();
            $table->timestamps();

            $table->foreign('dispositivo_id')->references('id')->on('dispositivos')->onDelete('cascade');
            $table->foreign('informe_id')->references('id')->on('informes')->onDelete('cascade');

            $table->unique(['dispositivo_id', 'informe_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispositivo_informe');
    }
}
