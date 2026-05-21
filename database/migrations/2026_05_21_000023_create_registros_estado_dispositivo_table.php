<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_estado_dispositivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->boolean('habilitado');
            $table->timestamp('modificado_en');

            $table->index(['user_id', 'dispositivo_id', 'modificado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_estado_dispositivo');
    }
};
