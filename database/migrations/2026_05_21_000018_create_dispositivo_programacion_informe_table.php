<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivo_programacion_informe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->foreignId('programacion_informe_id')->constrained('programacion_informes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dispositivo_id', 'programacion_informe_id'], 'dispro_prog_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivo_programacion_informe');
    }
};
