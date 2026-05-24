<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_alerta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regla_id')->nullable()->constrained('reglas')->nullOnDelete();
            $table->string('nombre_regla');
            $table->foreignId('dispositivo_id')->nullable()->constrained('dispositivos')->nullOnDelete();
            $table->string('nombre_dispositivo');
            $table->enum('tipo', ['firing', 'resolution']);
            $table->json('canales')->nullable();
            $table->text('mensaje');
            $table->timestamp('creado_en')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['user_id', 'creado_en']);
            $table->index(['user_id', 'nombre_dispositivo']);
            $table->index(['user_id', 'nombre_regla']);
            $table->index('creado_en');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_alerta');
    }
};
