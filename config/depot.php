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

    'version' => env('DEPOT_VERSION', '1.0.0'),

    'github_repo' => env('DEPOT_GITHUB_REPO', null),
];
