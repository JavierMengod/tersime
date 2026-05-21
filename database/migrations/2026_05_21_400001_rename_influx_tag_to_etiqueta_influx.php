<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->renameColumn('influx_tag', 'etiqueta_influx');
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->renameColumn('etiqueta_influx', 'influx_tag');
        });
    }
};
