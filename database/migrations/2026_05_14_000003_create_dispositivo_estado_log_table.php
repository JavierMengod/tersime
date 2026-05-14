<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDispositivoEstadoLogTable extends Migration
{
    public function up()
    {
        Schema::create('dispositivo_estado_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->onDelete('cascade');
            $table->boolean('habilitado');
            $table->timestamp('changed_at');

            $table->index(['user_id', 'dispositivo_id', 'changed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dispositivo_estado_log');
    }
}
