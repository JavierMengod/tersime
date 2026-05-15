<?php

namespace App\Console\Commands;

use App\Http\Controllers\GrafanaController;
use Illuminate\Console\Command;

class ComprobarFuncion extends Command
{
    protected $signature   = 'comprobar:funcion';
    protected $description = 'Comprueba conectividad con Grafana y lista dispositivos activos.';

    public function handle(GrafanaController $grafana): int
    {
        $devices = $grafana->checkDevices();
        $this->info('Dispositivos encontrados: ' . count($devices));
        return 0;
    }
}
