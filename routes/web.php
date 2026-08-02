<?php

use Illuminate\Support\Facades\Route;

// /storage/{path} is served by the public disk (filesystems.disks.public.serve).
// Exclude it from the SPA catch-all so uploads/logos are not replaced by app HTML.
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|storage|local-storage).*$');
