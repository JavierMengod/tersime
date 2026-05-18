<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToInformesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('informes', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('activo');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('informes', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
}
