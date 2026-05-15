<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedConnectionSettings extends Migration
{
    public function up()
    {
        $defaults = [
            'influxdb_url'            => env('INFLUXDB_URL', 'http://localhost:8086'),
            'influxdb_org'            => env('INFLUXDB_ORG', 'tersime'),
            'influxdb_bucket'         => env('INFLUX_BUCKET', 'PINZAS'),
            'influxdb_token'          => env('INFLUXDB_TOKEN', ''),
            'grafana_api_key'         => env('GRAFANA_API_KEY', ''),
            'grafana_datasource_id'   => env('GRAFANA_DATASOURCE_ID', '3'),
            'grafana_renderer_url'    => env('GRAFANA_RENDERER_URL', 'http://localhost:8081/render'),
            'predictor_timeout'       => '120',
            'predictor_default_hours' => '24',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down()
    {
        DB::table('settings')->whereIn('key', [
            'influxdb_url', 'influxdb_org', 'influxdb_bucket', 'influxdb_token',
            'grafana_api_key', 'grafana_datasource_id', 'grafana_renderer_url',
            'predictor_timeout', 'predictor_default_hours',
        ])->delete();
    }
}
