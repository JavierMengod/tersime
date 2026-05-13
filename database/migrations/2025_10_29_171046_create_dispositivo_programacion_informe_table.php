<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispositivoProgramacionInformeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dispositivo_programacion_informe', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispositivo_id')
                  ->constrained('dispositivos')
                  ->onDelete('cascade');

            $table->foreignId('programacion_informe_id')
                  ->constrained('programacion_informes')
                  ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['dispositivo_id', 'programacion_informe_id'], 'dispro_prog_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dispositivo_programacion_informe');
    }
}
