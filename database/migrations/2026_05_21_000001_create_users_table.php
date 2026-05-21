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
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('language')->default('es');
            $table->string('timezone')->default('UTC+01:00');
            $table->string('theme')->default('light');
            $table->decimal('coste_kwh', 8, 4)->default(0.15);
            $table->boolean('debug_mode')->default(false);
            $table->boolean('admin')->default(false);
            $table->boolean('enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
