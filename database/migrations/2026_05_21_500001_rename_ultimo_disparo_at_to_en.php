<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglas', function (Blueprint $table) {
            $table->renameColumn('ultimo_disparo_at', 'ultimo_disparo_en');
        });

        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->renameColumn('ultimo_disparo_at', 'ultimo_disparo_en');
        });
    }

    public function down(): void
    {
        Schema::table('reglas', function (Blueprint $table) {
            $table->renameColumn('ultimo_disparo_en', 'ultimo_disparo_at');
        });

        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->renameColumn('ultimo_disparo_en', 'ultimo_disparo_at');
        });
    }
};
