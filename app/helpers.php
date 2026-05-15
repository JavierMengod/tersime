<?php

if (!function_exists('sortUrl')) {
    function sortUrl(string $column, string $currentSort, string $currentDir): string
    {
        $dir = ($currentSort === $column && $currentDir === 'desc') ? 'asc' : 'desc';
        return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => 1]);
    }
}

if (!function_exists('sortIcon')) {
    function sortIcon(string $column, string $currentSort, string $currentDir): string
    {
        if ($currentSort !== $column) return 'fa-sort text-muted';
        return $currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    }
}
