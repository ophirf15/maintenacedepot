<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CapexExportService;

class CapexController extends Controller
{
    public function __construct(private CapexExportService $capex) {}

    public function forecast()
    {
        return response()->json(['data' => $this->capex->forecast()]);
    }

    public function exportExcel()
    {
        return $this->capex->excel();
    }

    public function exportPdf()
    {
        return $this->capex->pdf();
    }
}
