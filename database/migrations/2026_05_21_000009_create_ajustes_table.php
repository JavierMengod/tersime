<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave')->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        $defaults = [
            'alert_log_retention_days' => '90',
            'report_retention_days'    => '180',
            'login_max_attempts'       => '5',
            'login_throttle_minutes'   => '30',
            'influxdb_url'             => env('INFLUXDB_URL', ''),
            'influxdb_org'             => env('INFLUXDB_ORG', ''),
            'influxdb_bucket'          => env('INFLUX_BUCKET', 'PINZAS'),
            'influxdb_token'           => env('INFLUXDB_TOKEN', ''),
            'grafana_base_url'         => env('GRAFANA_BASE_URL', ''),
            'grafana_api_key'          => env('GRAFANA_API_KEY', ''),
            'grafana_datasource_id'    => env('GRAFANA_DATASOURCE_ID', '3'),
            'grafana_renderer_url'     => env('GRAFANA_RENDERER_URL', ''),
            'predictor_url'            => env('PREDICTOR_URL', ''),
            'predictor_timeout'        => '120',
            'predictor_default_hours'  => '24',
            'openrouter_model'         => env('OPENROUTER_MODEL', ''),
            'openrouter_api_key'       => env('OPENROUTER_API_KEY', ''),
        ];

        foreach ($defaults as $clave => $valor) {
            DB::table('ajustes')->insert([
                'clave'      => $clave,
                'valor'      => $valor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
