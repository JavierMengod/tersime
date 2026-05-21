<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('operador', ['>', '<', '==', '!=', '>=', '<='])->default('==');
            $table->unsignedSmallInteger('duracion')->default(0);
            $table->string('valor_comparacion');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_disparo_en')->nullable();
            $table->boolean('correo_activo')->default(false);
            $table->boolean('telegram_activo')->default(false);
            $table->boolean('discord_activo')->default(false);
            $table->string('correo_destinatario')->nullable();
            $table->text('plantilla_telegram')->nullable();
            $table->text('plantilla_correo')->nullable();
            $table->text('plantilla_discord')->nullable();
            $table->timestamps();

            $table->unique(['nombre', 'user_id'], 'reglas_nombre_user_unique');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas');
    }
};
