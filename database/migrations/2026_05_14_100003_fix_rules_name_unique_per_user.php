<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixRulesNameUniquePerUser extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS rules_name_unique');
            DB::statement('CREATE UNIQUE INDEX rules_name_user_unique ON rules (name, user_id)');
            return;
        }

        try {
            Schema::table('rules', function (Blueprint $table) {
                $table->dropUnique('rules_name_unique');
            });
        } catch (\Throwable $e) {}

        Schema::table('rules', function (Blueprint $table) {
            $table->unique(['name', 'user_id'], 'rules_name_user_unique');
        });
    }

    public function down()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS rules_name_user_unique');
            DB::statement('CREATE UNIQUE INDEX rules_name_unique ON rules (name)');
            return;
        }

        try {
            Schema::table('rules', function (Blueprint $table) {
                $table->dropUnique('rules_name_user_unique');
            });
        } catch (\Throwable $e) {}

        Schema::table('rules', function (Blueprint $table) {
            $table->unique('name', 'rules_name_unique');
        });
    }
}
