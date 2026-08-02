<?php

namespace App\Services;

use Illuminate\Support\Str;

class ReferenceGenerator
{
    public function make(string $prefix): string
    {
        return sprintf(
            '%s-%s-%s',
            strtoupper($prefix),
            now()->format('Y'),
            strtoupper(Str::random(6))
        );
    }

    public function qrToken(): string
    {
        return Str::lower(Str::random(24));
    }

    public function assetTag(string $prefix = 'AST'): string
    {
        return strtoupper($prefix).'-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    /**
     * Unique 6-digit tool number for labels / keypad entry (100000–999999).
     * Always exactly six digits so short typos like "11" cannot match "1".
     */
    public function numericCode(): string
    {
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $code = (string) random_int(100000, 999999);
            if (! \App\Models\Item::query()->where('numeric_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate a unique 6-digit tool number.');
    }
}
