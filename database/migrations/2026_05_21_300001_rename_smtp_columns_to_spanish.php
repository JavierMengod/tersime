<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credenciales_smtp', function (Blueprint $table) {
            $table->renameColumn('port',         'puerto');
            $table->renameColumn('username',     'usuario');
            $table->renameColumn('password',     'contrasena');
            $table->renameColumn('encryption',   'cifrado');
            $table->renameColumn('from_address', 'direccion_remitente');
        });
    }

    public function down(): void
    {
        Schema::table('credenciales_smtp', function (Blueprint $table) {
            $table->renameColumn('puerto',              'port');
            $table->renameColumn('usuario',             'username');
            $table->renameColumn('contrasena',          'password');
            $table->renameColumn('cifrado',             'encryption');
            $table->renameColumn('direccion_remitente', 'from_address');
        });
    }
};
