<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixRulesNameUniquePerUser extends Migration
{
    public function up()
    {
        // Eliminar índice único global y reemplazar por único por usuario
        DB::statement('DROP INDEX IF EXISTS rules_name_unique');
        DB::statement('CREATE UNIQUE INDEX rules_name_user_unique ON rules (name, user_id)');
    }

    public function down()
    {
        DB::statement('DROP INDEX IF EXISTS rules_name_user_unique');
        DB::statement('CREATE UNIQUE INDEX rules_name_unique ON rules (name)');
    }
}
