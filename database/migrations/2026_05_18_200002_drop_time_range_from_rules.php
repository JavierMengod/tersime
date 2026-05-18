<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class DropTimeRangeFromRules extends Migration
{
    public function up()
    {
        DB::statement('CREATE TABLE rules_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            operator VARCHAR(10) NOT NULL DEFAULT \'==\',
            comparison_value VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 0,
            telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
            discord_enabled TINYINT(1) NOT NULL DEFAULT 0,
            recipient_email VARCHAR(255),
            template_telegram TEXT,
            template_email TEXT,
            template_discord TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            last_triggered_at DATETIME,
            for_duration INTEGER NOT NULL DEFAULT 0
        )');

        DB::statement('INSERT INTO rules_new
            SELECT id, name, user_id, operator, comparison_value, is_active,
                   email_enabled, telegram_enabled, discord_enabled, recipient_email,
                   template_telegram, template_email, template_discord,
                   created_at, updated_at, last_triggered_at, for_duration
            FROM rules');

        DB::statement('DROP TABLE rules');
        DB::statement('ALTER TABLE rules_new RENAME TO rules');

        DB::statement('CREATE UNIQUE INDEX rules_name_user_unique ON rules (name, user_id)');
    }

    public function down()
    {
        DB::statement('CREATE TABLE rules_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            operator VARCHAR(10) NOT NULL DEFAULT \'==\',
            time_range INTEGER NOT NULL DEFAULT 0,
            comparison_value VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 0,
            telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
            discord_enabled TINYINT(1) NOT NULL DEFAULT 0,
            recipient_email VARCHAR(255),
            template_telegram TEXT,
            template_email TEXT,
            template_discord TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            last_triggered_at DATETIME,
            for_duration INTEGER NOT NULL DEFAULT 0
        )');

        DB::statement('INSERT INTO rules_new
            SELECT id, name, user_id, operator, 0,
                   comparison_value, is_active,
                   email_enabled, telegram_enabled, discord_enabled, recipient_email,
                   template_telegram, template_email, template_discord,
                   created_at, updated_at, last_triggered_at, for_duration
            FROM rules');

        DB::statement('DROP TABLE rules');
        DB::statement('ALTER TABLE rules_new RENAME TO rules');

        DB::statement('CREATE UNIQUE INDEX rules_name_user_unique ON rules (name, user_id)');
    }
}
