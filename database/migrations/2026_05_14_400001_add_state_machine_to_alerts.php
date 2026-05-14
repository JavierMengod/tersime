<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStateMachineToAlerts extends Migration
{
    public function up()
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('for_duration')->default(0)->after('time_range');
        });

        Schema::table('dispositivo_rule', function (Blueprint $table) {
            $table->string('alert_state', 10)->default('ok')->after('last_triggered_at');
            $table->timestamp('pending_since')->nullable()->after('alert_state');
        });
    }

    public function down()
    {
        // SQLite 3.34 no soporta DROP COLUMN
    }
}
