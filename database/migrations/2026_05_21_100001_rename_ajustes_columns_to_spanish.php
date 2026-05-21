<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes', function (Blueprint $table) {
            $table->renameColumn('key',   'clave');
            $table->renameColumn('value', 'valor');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes', function (Blueprint $table) {
            $table->renameColumn('clave', 'key');
            $table->renameColumn('valor', 'value');
        });
    }
};
