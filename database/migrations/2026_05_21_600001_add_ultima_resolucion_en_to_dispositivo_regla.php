<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->timestamp('ultima_resolucion_en')->nullable()->after('pending_since');
        });
    }

    public function down(): void
    {
        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->dropColumn('ultima_resolucion_en');
        });
    }
};
