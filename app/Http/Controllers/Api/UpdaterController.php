<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\UpdaterService;
use Illuminate\Http\Request;

class UpdaterController extends Controller
{
    public function __construct(private UpdaterService $updater, private AuditLogger $audit) {}

    public function check()
    {
        return response()->json(['data' => $this->updater->checkForUpdates()]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'download_url' => 'nullable|url',
        ]);

        $result = $this->updater->applyUpdate($data['download_url'] ?? null);

        $this->audit->log('update_applied', null, null, $result);

        return response()->json(['data' => $result], $result['ok'] ? 200 : 422);
    }
}
