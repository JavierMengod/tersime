<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $defaults = [
            'alert_log_retention_days' => '90',
            'report_retention_days'    => '180',
            'login_max_attempts'       => '5',
            'login_throttle_minutes'   => '30',
            'grafana_base_url'         => env('GRAFANA_BASE_URL', ''),
            'predictor_url'            => env('PREDICTOR_URL', ''),
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
