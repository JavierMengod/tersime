<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['Demanda', 'Programado'])->default('Demanda');
            $table->string('nombre_archivo')->nullable()->unique();
            $table->string('pdf_path')->nullable();
            $table->date('periodo_from')->nullable();
            $table->date('periodo_to')->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->timestamp('generado_en')->nullable();
            $table->boolean('telegram')->default(false);
            $table->boolean('discord')->default(false);
            $table->boolean('correo')->default(false);
            $table->string('correo_destino')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('estado')->default('pending');
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};
