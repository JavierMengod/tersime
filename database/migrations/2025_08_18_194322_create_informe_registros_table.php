<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInformeRegistrosTable extends Migration
{
    public function up()
    {
        Schema::create('informe_registros', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->unsignedBigInteger('informe_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();

            // Datos del archivo
            $table->string('nombre_archivo');   // ej. informe_20250801_1234.pdf
            $table->string('pdf_path');         // storage/app/public/informes/...

            // Periodo cubierto
            $table->date('periodo_from')->nullable();
            $table->date('periodo_to')->nullable();

            // Extras en JSON
            $table->json('dispositivos')->nullable();   // ids + nombres de dispositivos
            $table->json('notificaciones')->nullable(); // qué canales se usaron

            // Info adicional
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->timestamps();

            // Claves foráneas
            $table->foreign('informe_id')
                ->references('id')->on('informes')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('informe_registros');
    }
}
