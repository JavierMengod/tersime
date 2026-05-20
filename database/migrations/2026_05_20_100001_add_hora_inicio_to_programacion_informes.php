<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->string('hora_inicio', 5)->nullable()->after('valor_periodo');
        });
    }

    public function down(): void
    {
        Schema::table('programacion_informes', function (Blueprint $table) {
            $table->dropColumn('hora_inicio');
        });
    }
};
