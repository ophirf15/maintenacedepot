<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Depot Borrow Platform
    |--------------------------------------------------------------------------
    |
    | Application-level configuration for the Depot Borrow Platform, used by
    | the UpdaterService and installer to report/compare versions and to
    | know where to check for released updates.
    |
    */

    /*
    | Prefer DEPOT_VERSION from .env when set; otherwise the VERSION file written
    | by release packaging / the in-app updater.
    */
    'version' => filled(env('DEPOT_VERSION'))
        ? env('DEPOT_VERSION')
        : (is_file(base_path('VERSION'))
            ? trim((string) file_get_contents(base_path('VERSION')))
            : '1.0.5'),

    'github_repo' => env('DEPOT_GITHUB_REPO', null),
];
