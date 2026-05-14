<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFromAddressToSmtpCredentials extends Migration
{
    public function up()
    {
        Schema::table('smtp_credentials', function (Blueprint $table) {
            $table->string('from_address')->nullable()->after('username');
        });
    }

    public function down()
    {
        // SQLite 3.34 no soporta DROP COLUMN
    }
}
