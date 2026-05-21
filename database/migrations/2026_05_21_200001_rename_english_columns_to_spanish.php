<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Credenciales
        Schema::table('credenciales_discord',  fn($t) => $t->renameColumn('active', 'activo'));
        Schema::table('credenciales_smtp',     fn($t) => $t->renameColumn('active', 'activo'));
        Schema::table('credenciales_telegram', fn($t) => $t->renameColumn('active', 'activo'));

        // MedioNotificacion
        Schema::table('medios_notificacion', function (Blueprint $table) {
            $table->renameColumn('channel', 'canal');
            $table->renameColumn('active',  'activo');
            $table->renameColumn('config',  'configuracion');
        });

        // Regla
        Schema::table('reglas', function (Blueprint $table) {
            $table->renameColumn('name',              'nombre');
            $table->renameColumn('operator',          'operador');
            $table->renameColumn('for_duration',      'duracion');
            $table->renameColumn('comparison_value',  'valor_comparacion');
            $table->renameColumn('is_active',         'activo');
            $table->renameColumn('last_triggered_at', 'ultimo_disparo_at');
            $table->renameColumn('email_enabled',     'correo_activo');
            $table->renameColumn('telegram_enabled',  'telegram_activo');
            $table->renameColumn('discord_enabled',   'discord_activo');
            $table->renameColumn('recipient_email',   'correo_destinatario');
            $table->renameColumn('template_telegram', 'plantilla_telegram');
            $table->renameColumn('template_email',    'plantilla_correo');
            $table->renameColumn('template_discord',  'plantilla_discord');
        });

        // Pivot dispositivo_regla
        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->renameColumn('rule_id',          'regla_id');
            $table->renameColumn('last_triggered_at','ultimo_disparo_at');
        });

        // RegistroAlerta
        Schema::table('registros_alerta', function (Blueprint $table) {
            $table->renameColumn('rule_id',     'regla_id');
            $table->renameColumn('rule_name',   'nombre_regla');
            $table->renameColumn('device_name', 'nombre_dispositivo');
            $table->renameColumn('type',        'tipo');
            $table->renameColumn('channels',    'canales');
            $table->renameColumn('message',     'mensaje');
        });

        // Informe
        Schema::table('informes', function (Blueprint $table) {
            $table->renameColumn('status',        'estado');
            $table->renameColumn('error_message', 'mensaje_error');
            $table->renameColumn('size_bytes',    'tamano_bytes');
            $table->renameColumn('generated_at',  'generado_en');
        });

        // ProgramacionInformes
        Schema::table('programacion_informes', fn($t) => $t->renameColumn('last_run_at', 'ultima_ejecucion_at'));

        // RegistroEstadoDispositivo
        Schema::table('registros_estado_dispositivo', fn($t) => $t->renameColumn('changed_at', 'modificado_en'));
    }

    public function down(): void
    {
        Schema::table('credenciales_discord',  fn($t) => $t->renameColumn('activo', 'active'));
        Schema::table('credenciales_smtp',     fn($t) => $t->renameColumn('activo', 'active'));
        Schema::table('credenciales_telegram', fn($t) => $t->renameColumn('activo', 'active'));

        Schema::table('medios_notificacion', function (Blueprint $table) {
            $table->renameColumn('canal',        'channel');
            $table->renameColumn('activo',       'active');
            $table->renameColumn('configuracion','config');
        });

        Schema::table('reglas', function (Blueprint $table) {
            $table->renameColumn('nombre',            'name');
            $table->renameColumn('operador',          'operator');
            $table->renameColumn('duracion',          'for_duration');
            $table->renameColumn('valor_comparacion', 'comparison_value');
            $table->renameColumn('activo',            'is_active');
            $table->renameColumn('ultimo_disparo_at', 'last_triggered_at');
            $table->renameColumn('correo_activo',     'email_enabled');
            $table->renameColumn('telegram_activo',   'telegram_enabled');
            $table->renameColumn('discord_activo',    'discord_enabled');
            $table->renameColumn('correo_destinatario','recipient_email');
            $table->renameColumn('plantilla_telegram','template_telegram');
            $table->renameColumn('plantilla_correo',  'template_email');
            $table->renameColumn('plantilla_discord', 'template_discord');
        });

        Schema::table('dispositivo_regla', function (Blueprint $table) {
            $table->renameColumn('regla_id',          'rule_id');
            $table->renameColumn('ultimo_disparo_at',  'last_triggered_at');
        });

        Schema::table('registros_alerta', function (Blueprint $table) {
            $table->renameColumn('regla_id',           'rule_id');
            $table->renameColumn('nombre_regla',       'rule_name');
            $table->renameColumn('nombre_dispositivo', 'device_name');
            $table->renameColumn('tipo',               'type');
            $table->renameColumn('canales',            'channels');
            $table->renameColumn('mensaje',            'message');
        });

        Schema::table('informes', function (Blueprint $table) {
            $table->renameColumn('estado',       'status');
            $table->renameColumn('mensaje_error','error_message');
            $table->renameColumn('tamano_bytes', 'size_bytes');
            $table->renameColumn('generado_en',  'generated_at');
        });

        Schema::table('programacion_informes',        fn($t) => $t->renameColumn('ultima_ejecucion_at', 'last_run_at'));
        Schema::table('registros_estado_dispositivo', fn($t) => $t->renameColumn('modificado_en', 'changed_at'));
    }
};
