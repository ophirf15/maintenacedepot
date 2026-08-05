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

    /*
    | Hosts the updater may download release packages from. A request-supplied
    | download_url outside this list is refused before maintenance mode starts.
    | Override with a comma-separated DEPOT_UPDATE_ALLOWED_HOSTS for mirrors.
    */
    'update_allowed_hosts' => array_values(array_filter(array_map(
        static fn ($host) => strtolower(trim((string) $host)),
        explode(',', (string) env('DEPOT_UPDATE_ALLOWED_HOSTS', implode(',', [
            'api.github.com',
            'github.com',
            'codeload.github.com',
            'objects.githubusercontent.com',
            'release-assets.githubusercontent.com',
        ])))
    ))),
];
