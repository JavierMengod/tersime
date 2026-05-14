<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastTriggeredAtToDispositivoRule extends Migration
{
    public function up()
    {
        Schema::table('dispositivo_rule', function (Blueprint $table) {
            $table->timestamp('last_triggered_at')->nullable()->after('dispositivo_id');
        });
    }

    public function down()
    {
        // SQLite 3.34 no soporta DROP COLUMN
    }
}
