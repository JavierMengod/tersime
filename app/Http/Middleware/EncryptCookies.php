<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [];

    // Las cookies de Grafana no están cifradas por Laravel; pasarlas tal cual
    // para que el GrafanaProxyController las reenvíe correctamente.
    public function isDisabled($name)
    {
        return str_starts_with($name, 'grafana_') || parent::isDisabled($name);
    }
}
