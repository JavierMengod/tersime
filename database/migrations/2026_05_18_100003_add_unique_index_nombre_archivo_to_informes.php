<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexNombreArchivoToInformes extends Migration
{
    public function up()
    {
        Schema::table('informes', function (Blueprint $table) {
            $table->unique('nombre_archivo');
        });
    }

    public function down()
    {
        Schema::table('informes', function (Blueprint $table) {
            $table->dropUnique(['nombre_archivo']);
        });
    }
}
