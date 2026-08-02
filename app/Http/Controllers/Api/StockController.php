<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {}

    public function consumables(Request $request)
    {
        $query = Item::query()
            ->where('is_consumable', true)
            ->with(['toolType', 'depot', 'status']);

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_qty', '<=', 'reorder_point');
        }

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('asset_tag', 'like', "%{$q}%")
                    ->orWhere('supplier_name', 'like', "%{$q}%")
                    ->orWhere('supplier_part_number', 'like', "%{$q}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->paginate($request->integer('per_page', 50)),
        ]);
    }

    public function movements(Request $request)
    {
        $query = StockMovement::query()->with(['item', 'user'])->orderByDesc('id');

        if ($itemId = $request->integer('item_id')) {
            $query->where('item_id', $itemId);
        }

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 50)),
        ]);
    }

    public function restock(Request $request, Item $item)
    {
        $data = $request->validate([
            'qty' => 'required|numeric|gt:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $updated = $this->stock->restock($item, (float) $data['qty'], $request->user(), $data['notes'] ?? null);
        $this->audit->log('stock_restock', $updated, null, ['qty' => $data['qty']]);

        return response()->json(['data' => $updated]);
    }

    public function adjust(Request $request, Item $item)
    {
        $data = $request->validate([
            'qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $updated = $this->stock->adjustTo($item, (float) $data['qty'], $request->user(), $data['notes'] ?? null);
        $this->audit->log('stock_adjust', $updated, null, ['qty' => $data['qty']]);

        return response()->json(['data' => $updated]);
    }
}
