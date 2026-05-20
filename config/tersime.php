<?php

return [

    'grafana' => [
        'renderer_url'      => env('GRAFANA_RENDERER_URL', 'http://localhost:8081/render'),
        'renderer_base_url' => env('GRAFANA_RENDERER_BASE_URL'),
        'renderer_width'    => env('GRAFANA_RENDERER_WIDTH', 1000),
        'renderer_height'   => env('GRAFANA_RENDERER_HEIGHT', 500),
        'renderer_timeout'  => env('GRAFANA_RENDERER_TIMEOUT', 90),
        'renderer_token'    => env('GRAFANA_RENDERER_TOKEN'),
        'api_key'           => env('GRAFANA_API_KEY'),
    ],

    'anomalias' => [
        'multiplicador' => env('MULTIPLICADOR_ANOMALIAS', 3.5),
    ],

    'costes' => [
        'kwh' => env('COSTE_ESTIMADO_KWH', 0.15),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model'   => env('OPENROUTER_MODEL', 'tngtech/deepseek-r1t2-chimera:free'),
    ],

    'influxdb' => [
        'url'    => env('INFLUXDB_URL', 'http://localhost:8086'),
        'org'    => env('INFLUXDB_ORG', 'tersime'),
        'token'  => env('INFLUXDB_TOKEN', ''),
        'bucket' => env('INFLUX_BUCKET', 'PINZAS'),
    ],

];
