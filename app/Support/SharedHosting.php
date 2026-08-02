<?php

namespace App\Support;

/**
 * Bluehost-style deploys often place index.php next to artisan (document root = app root)
 * instead of using the Laravel public/ directory as the web root.
 */
class SharedHosting
{
    public static function isFlattened(?string $basePath = null): bool
    {
        $base = $basePath ?? base_path();

        return is_file($base.DIRECTORY_SEPARATOR.'index.php')
            && is_file($base.DIRECTORY_SEPARATOR.'artisan');
    }

    /** index.php for document-root = app-root installs (paths are same-directory). */
    public static function flattenedIndexPhp(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());

PHP;
    }
}
