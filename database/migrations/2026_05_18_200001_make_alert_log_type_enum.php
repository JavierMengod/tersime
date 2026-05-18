<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeAlertLogTypeEnum extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE alert_logs_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                rule_id INTEGER REFERENCES rules(id) ON DELETE SET NULL,
                rule_name VARCHAR(255) NOT NULL,
                dispositivo_id INTEGER REFERENCES dispositivos(id) ON DELETE SET NULL,
                device_name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL CHECK(type IN (\'firing\', \'resolution\')),
                channels VARCHAR(100),
                message TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )');

            DB::statement('INSERT INTO alert_logs_new SELECT * FROM alert_logs');
            DB::statement('DROP TABLE alert_logs');
            DB::statement('ALTER TABLE alert_logs_new RENAME TO alert_logs');

            DB::statement('CREATE INDEX alert_logs_user_id_created_at_index ON alert_logs (user_id, created_at)');
            DB::statement('CREATE INDEX alert_logs_user_id_device_name_index ON alert_logs (user_id, device_name)');
            DB::statement('CREATE INDEX alert_logs_user_id_rule_name_index ON alert_logs (user_id, rule_name)');
            return;
        }

        DB::statement("ALTER TABLE alert_logs MODIFY type ENUM('firing', 'resolution') NOT NULL");
    }

    public function down()
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE alert_logs_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                rule_id INTEGER REFERENCES rules(id) ON DELETE SET NULL,
                rule_name VARCHAR(255) NOT NULL,
                dispositivo_id INTEGER REFERENCES dispositivos(id) ON DELETE SET NULL,
                device_name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL,
                channels VARCHAR(100),
                message TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )');

            DB::statement('INSERT INTO alert_logs_new SELECT * FROM alert_logs');
            DB::statement('DROP TABLE alert_logs');
            DB::statement('ALTER TABLE alert_logs_new RENAME TO alert_logs');

            DB::statement('CREATE INDEX alert_logs_user_id_created_at_index ON alert_logs (user_id, created_at)');
            DB::statement('CREATE INDEX alert_logs_user_id_device_name_index ON alert_logs (user_id, device_name)');
            DB::statement('CREATE INDEX alert_logs_user_id_rule_name_index ON alert_logs (user_id, rule_name)');
            return;
        }

        DB::statement('ALTER TABLE alert_logs MODIFY type VARCHAR(20) NOT NULL');
    }
}
