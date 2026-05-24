<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('idioma')->default('es');
            $table->string('zona_horaria')->default('UTC+01:00');
            $table->string('tema')->default('light');
            $table->decimal('coste_kwh', 8, 4)->default(0.15);
            $table->boolean('modo_depuracion')->default(false);
            $table->boolean('administrador')->default(false);
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
