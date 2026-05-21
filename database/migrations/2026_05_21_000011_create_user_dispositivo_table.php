<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_dispositivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->string('nombre')->nullable();
            $table->boolean('habilitado')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'dispositivo_id'], 'ud_user_device_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dispositivo');
    }
};
