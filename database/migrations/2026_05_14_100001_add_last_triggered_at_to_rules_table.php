<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastTriggeredAtToRulesTable extends Migration
{
    public function up()
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->timestamp('last_triggered_at')->nullable()->after('is_active');
        });
    }

    public function down()
    {
        // SQLite 3.34 no soporta DROP COLUMN
    }
}
