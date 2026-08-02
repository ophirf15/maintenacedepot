<?php

namespace App\Support;

/**
 * Same-origin public disk URLs so the SPA never opens a broken APP_URL host.
 */
class PublicStorageUrl
{
    public static function path(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
