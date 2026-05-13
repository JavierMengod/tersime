<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInformesTable extends Migration
{
    public function up()
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->id();

            // Usuario propietario
            $table->unsignedBigInteger('user_id')->index();

            // Información del informe
            $table->enum('tipo', ['Demanda', 'Programado'])->default('Demanda');

            
            $table->string('nombre_archivo')->nullable();   
            $table->string('pdf_path')->nullable();         
            $table->date('periodo_from')->nullable();
            $table->date('periodo_to')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('generated_at')->nullable();

            
            $table->boolean('telegram')->default(false);
            $table->boolean('discord')->default(false);
            $table->boolean('correo')->default(false);
            $table->string('correo_destino')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            // Relaciones
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('informes');
    }
}
