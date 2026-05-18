<?php

namespace App\Traits;

trait ResolvesInformePath
{
    private function resolveInformePath(?string $pdfPath): ?string
    {
        if (empty($pdfPath)) {
            return null;
        }

        // Already an absolute path on this system
        if (str_starts_with($pdfPath, '/')) {
            return $pdfPath;
        }

        $relative = ltrim($pdfPath, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative);
        $relative = preg_replace('#^public/#', '', $relative);
        $relative = preg_replace('#^storage/#', '', $relative);

        return storage_path('app/public/' . $relative);
    }
}
