<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivo_regla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regla_id')->constrained('reglas')->cascadeOnDelete();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->timestamp('ultimo_disparo_en')->nullable();
            $table->string('estado_alerta', 10)->default('ok');
            $table->timestamp('pendiente_desde')->nullable();
            $table->timestamp('ultima_resolucion_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivo_regla');
    }
};
