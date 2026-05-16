<?php

namespace App\Traits;

trait ResolvesInformePath
{
    /**
     * Devuelve la ruta absoluta al PDF del informe, normalizando cualquier prefijo
     * que pueda haberse grabado (absoluta, storage/app/public/..., public/...).
     */
    private function resolveInformePath(?string $pdfPath): ?string
    {
        if (empty($pdfPath)) {
            return null;
        }

        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $pdfPath) === 1) {
            return $pdfPath;
        }

        $relative = ltrim($pdfPath, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative);
        $relative = preg_replace('#^public/#', '', $relative);
        $relative = preg_replace('#^storage/#', '', $relative);

        return storage_path('app/public/' . $relative);
    }
}
