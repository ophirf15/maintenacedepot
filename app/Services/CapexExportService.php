<?php

namespace App\Services;

use App\Models\Item;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CapexExportService
{
    /** Assumed annual hours when tool tracks usage but no lifetime hours are set. */
    private const DEFAULT_ANNUAL_HOURS = 200;

    /** Repair spend at or above this share of replacement cost pulls the year forward. */
    private const REPAIR_PRESSURE_RATIO = 0.5;

    public function forecast(): Collection
    {
        $items = Item::query()
            ->with(['toolType.category', 'depot'])
            ->where(function ($q) {
                $q->whereNotNull('purchase_date')->orWhereNotNull('lifespan_years');
            })
            ->get();

        $repairSpend = WorkOrder::query()
            ->where('status', 'completed')
            ->whereIn('item_id', $items->pluck('id'))
            ->selectRaw('item_id, COALESCE(SUM(total_cost), 0) as spent')
            ->groupBy('item_id')
            ->pluck('spent', 'item_id');

        return $items
            ->map(function (Item $item) use ($repairSpend) {
                $years = $item->lifespan_years ?: 5;
                $purchase = $item->purchase_date;
                $baseYear = $purchase
                    ? $purchase->copy()->addYears($years)->year
                    : now()->addYears($years)->year;
                $replaceYear = $baseYear;
                $reasons = ['age'];

                $replacement = (float) ($item->replacement_cost ?: $item->purchase_price ?: 0);
                $salvage = (float) ($item->salvage_value ?: 0);
                $spent = (float) ($repairSpend[$item->id] ?? 0);

                if ($item->end_of_life_soon) {
                    $replaceYear = min($replaceYear, (int) now()->year);
                    $reasons[] = 'eol';
                }

                if ($item->condition === 'poor') {
                    $replaceYear = min($replaceYear, max((int) now()->year, $baseYear - 2));
                    $reasons[] = 'condition';
                }

                if ($item->toolType?->tracks_usage_hours) {
                    $expectedLifetime = max(1, $years * self::DEFAULT_ANNUAL_HOURS);
                    $ratio = (float) $item->usage_hours / $expectedLifetime;
                    if ($ratio >= 0.85) {
                        $pull = $ratio >= 1.0 ? 0 : 1;
                        $replaceYear = min($replaceYear, max((int) now()->year, $baseYear - (2 - $pull)));
                        $reasons[] = 'usage';
                    }
                }

                if ($replacement > 0 && $spent >= $replacement * self::REPAIR_PRESSURE_RATIO) {
                    $replaceYear = min($replaceYear, (int) now()->year + ($spent >= $replacement ? 0 : 1));
                    $reasons[] = 'repair_spend';
                }

                $reasons = array_values(array_unique($reasons));

                return [
                    'asset_tag' => $item->asset_tag,
                    'name' => $item->displayName(),
                    'category' => $item->toolType?->category?->name,
                    'tool_type' => $item->toolType?->name,
                    'depot' => $item->depot?->name,
                    'purchase_date' => optional($item->purchase_date)->toDateString(),
                    'purchase_price' => (float) $item->purchase_price,
                    'replacement_cost' => $replacement,
                    'salvage_value' => $salvage,
                    'net_replacement_cost' => max(0, $replacement - $salvage),
                    'lifespan_years' => $years,
                    'condition' => $item->condition,
                    'usage_hours' => (float) $item->usage_hours,
                    'repair_spend' => $spent,
                    'end_of_life_soon' => $item->end_of_life_soon ? 'Yes' : 'No',
                    'planned_replacement_year' => $replaceYear,
                    'suggest_replace_reasons' => $reasons,
                    'suggest_replace_reason' => implode(', ', $reasons),
                ];
            })
            ->sortBy('planned_replacement_year')
            ->values();
    }

    public function excel(): BinaryFileResponse
    {
        $rows = $this->forecast()->map(fn (array $row) => collect($row)->except('suggest_replace_reasons'));

        return Excel::download(new class($rows) implements FromCollection, WithHeadings, WithStyles
        {
            public function __construct(private Collection $rows) {}

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return [
                    'Asset Tag', 'Name', 'Category', 'Tool Type', 'Depot', 'Purchase Date',
                    'Purchase Price', 'Replacement Cost', 'Salvage Value', 'Net Replacement Cost',
                    'Lifespan Years', 'Condition', 'Usage Hours', 'Repair Spend', 'EOL Soon',
                    'Planned Replacement Year', 'Reasons',
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, 'depot-capex-plan.xlsx');
    }

    public function pdf()
    {
        $rows = $this->forecast();
        $byYear = $rows->groupBy('planned_replacement_year');

        $pdf = Pdf::loadView('exports.capex', [
            'rows' => $rows,
            'byYear' => $byYear,
            'generatedAt' => now(),
            'appName' => app(SettingsService::class)->get('branding', 'app_name', 'Maintenance Depot'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('depot-capex-plan.pdf');
    }
}
