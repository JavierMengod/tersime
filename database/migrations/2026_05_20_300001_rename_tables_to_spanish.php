<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('rules',                    'reglas');
        Schema::rename('alert_logs',               'registros_alerta');
        Schema::rename('settings',                 'ajustes');
        Schema::rename('notification_methods',     'medios_notificacion');
        Schema::rename('discord_credentials',      'credenciales_discord');
        Schema::rename('smtp_credentials',         'credenciales_smtp');
        Schema::rename('telegram_credentials',     'credenciales_telegram');
        Schema::rename('dispositivo_estado_log',   'registros_estado_dispositivo');
        Schema::rename('dispositivo_rule',         'dispositivo_regla');
    }

    public function down(): void
    {
        Schema::rename('reglas',                      'rules');
        Schema::rename('registros_alerta',            'alert_logs');
        Schema::rename('ajustes',                     'settings');
        Schema::rename('medios_notificacion',          'notification_methods');
        Schema::rename('credenciales_discord',         'discord_credentials');
        Schema::rename('credenciales_smtp',            'smtp_credentials');
        Schema::rename('credenciales_telegram',        'telegram_credentials');
        Schema::rename('registros_estado_dispositivo', 'dispositivo_estado_log');
        Schema::rename('dispositivo_regla',            'dispositivo_rule');
    }
};
