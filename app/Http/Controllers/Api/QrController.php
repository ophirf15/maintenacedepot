<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\QrLabelService;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrController extends Controller
{
    public function __construct(private QrLabelService $qr) {}

    public function sizes()
    {
        return response()->json(['data' => $this->qr->sizeCatalog()]);
    }

    public function generate(Request $request, Item $item)
    {
        $size = $this->validatedSize($request);
        $path = $this->qr->generatePng($item, $size);

        return response()->json([
            'data' => [
                'item_id' => $item->id,
                'asset_tag' => $item->asset_tag,
                'numeric_code' => $item->numeric_code,
                'qr_token' => $item->qr_token,
                'size' => $size,
                'path' => $path,
                'url' => PublicStorageUrl::path($path),
                'download_url' => "/api/qr/items/{$item->id}/label?size={$size}",
            ],
        ]);
    }

    public function label(Request $request, Item $item): StreamedResponse
    {
        $size = $this->validatedSize($request);
        $binary = $this->qr->pngBinary($item, $size);
        $filename = $item->asset_tag.'-'.$size.'-label.png';

        return response()->streamDownload(
            function () use ($binary) {
                echo $binary;
            },
            $filename,
            ['Content-Type' => 'image/png']
        );
    }

    /**
     * Draft preview — does not persist layout. Used by IT Admin label builder.
     */
    public function preview(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'size' => 'required|string',
            'item_id' => 'nullable|exists:items,id',
            'layout' => 'nullable|array',
        ]);

        $size = $this->validatedSize($request);
        $item = isset($data['item_id'])
            ? Item::query()->findOrFail($data['item_id'])
            : Item::query()->orderBy('id')->first();

        if (! $item) {
            abort(422, 'No sample item available to preview.');
        }

        $override = is_array($data['layout'] ?? null) ? $data['layout'] : null;
        $binary = $this->qr->pngBinary($item, $size, $override);

        return response()->streamDownload(
            function () use ($binary) {
                echo $binary;
            },
            'label-preview.png',
            ['Content-Type' => 'image/png']
        );
    }

    public function exportZip(Request $request)
    {
        $data = $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'exists:items,id',
            'all' => 'sometimes|boolean',
            'size' => 'nullable|string',
        ]);

        $size = $this->validatedSize($request);
        $ids = $this->qr->resolveItemIds($data['item_ids'] ?? null, (bool) ($data['all'] ?? false));
        if ($ids === []) {
            return response()->json(['message' => 'No tools to print labels for.'], 422);
        }

        $zipPath = $this->qr->exportZip($ids, $size);

        return response()->download($zipPath, "equipment-labels-{$size}.zip")->deleteFileAfterSend(true);
    }

    public function sheet(Request $request)
    {
        $data = $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'exists:items,id',
            'all' => 'sometimes|boolean',
            'size' => 'nullable|string',
        ]);

        $size = $this->validatedSize($request);
        $ids = $this->qr->resolveItemIds($data['item_ids'] ?? null, (bool) ($data['all'] ?? false));
        if ($ids === []) {
            return response()->json(['message' => 'No tools to print labels for.'], 422);
        }

        $pdf = $this->qr->exportSheetPdf($ids, $size);

        return $pdf->download("equipment-label-sheet-{$size}.pdf");
    }

    protected function validatedSize(Request $request): string
    {
        $size = $request->input('size', QrLabelService::DEFAULT_SIZE);
        // resolveSize throws ValidationException on unknown keys
        $this->qr->resolveSize(is_string($size) ? $size : QrLabelService::DEFAULT_SIZE);

        return is_string($size) && $size !== '' ? $size : QrLabelService::DEFAULT_SIZE;
    }
}
