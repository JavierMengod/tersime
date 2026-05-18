<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class CsvArray implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        if (empty($value)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) return null;

        if (!is_array($value)) {
            throw new \InvalidArgumentException("CsvArray cast expects array, got " . gettype($value));
        }

        $filtered = array_values(array_filter(array_map('trim', $value)));
        return $filtered ? implode(',', $filtered) : null;
    }
}
