<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medios_notificacion', function (Blueprint $table) {
            $table->id();
            $table->enum('canal', ['telegram', 'email', 'discord'])->unique();
            $table->boolean('activo')->default(false);
            $table->json('configuracion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medios_notificacion');
    }
};
