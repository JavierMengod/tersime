<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programacion_informes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo_periodo', 10)->default('horas');
            $table->unsignedInteger('valor_periodo')->default(1);
            $table->string('hora_inicio', 5)->nullable();
            $table->boolean('telegram')->default(false);
            $table->boolean('discord')->default(false);
            $table->boolean('correo')->default(false);
            $table->string('correo_destino')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_ejecucion_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programacion_informes');
    }
};
