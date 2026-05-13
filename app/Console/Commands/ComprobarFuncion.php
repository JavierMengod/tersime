<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\GrafanaController;

class ComprobarFuncion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comprobar:funcion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $grafana = new GrafanaController();
        $grafana -> checkDevices();
        return 0;
    }
}
