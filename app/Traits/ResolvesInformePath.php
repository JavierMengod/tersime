<?php

namespace App\Traits;

trait ResolvesInformePath
{
    private function resolverRutaInforme(?string $rutaPdf): ?string
    {
        if (empty($rutaPdf)) {
            return null;
        }

        if (str_starts_with($rutaPdf, '/')) {
            return $rutaPdf;
        }

        $relativa = ltrim($rutaPdf, '/');
        $relativa = preg_replace('#^storage/app/public/#', '', $relativa);
        $relativa = preg_replace('#^public/#', '', $relativa);
        $relativa = preg_replace('#^storage/#', '', $relativa);

        return storage_path('app/public/' . $relativa);
    }
}
