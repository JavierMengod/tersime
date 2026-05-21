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
        $dispositivos = $grafana->verificarDispositivos();
        $this->info('Dispositivos encontrados: ' . count($dispositivos));
        return 0;
    }
}
