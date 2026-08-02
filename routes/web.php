<?php

use Illuminate\Support\Facades\Route;

// Static front-door assets must never fall through to the SPA HTML shell
// (browsers reject JS modules served as text/html — classic Vite MIME error).
Route::view('/{any?}', 'app')->where(
    'any',
    '^(?!api|sanctum|up|storage|local-storage|build|sw\.js|manifest\.webmanifest|favicon\.ico|favicon\.svg|icons).*$'
);
