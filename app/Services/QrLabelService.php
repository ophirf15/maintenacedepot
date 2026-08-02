<?php

namespace App\Services;

use App\Models\Item;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Picqer\Barcode\BarcodeGeneratorPNG;
use ZipArchive;

class QrLabelService
{
    public const DEFAULT_SIZE = 'standard';

    public function __construct(private SettingsService $settings) {}

    /**
     * Ownership / return-to line from IT Admin → Branding (empty = omit / use scan hint).
     */
    public function ownershipLine(): string
    {
        $line = trim((string) $this->settings->get('branding', 'label_ownership', ''));

        return $line;
    }

    /**
     * Default field toggles per size (booleans only).
     *
     * @return array<string, bool>
     */
    public static function defaultFieldsFor(string $sizeKey): array
    {
        $preset = self::sizes()[$sizeKey] ?? null;
        if ($preset === null) {
            return [];
        }

        if (($preset['layout'] ?? '') === 'compact') {
            return [
                'qr' => true,
                'numeric_id' => true,
                'name' => true,
                'ownership' => true,
            ];
        }

        return [
            'qr' => true,
            'numeric_id' => true,
            'name' => true,
            'asset_tag' => true,
            'barcode' => true,
            'ownership' => true,
        ];
    }

    /**
     * Placement / typography options per size.
     *
     * @return array{qr_side: string, stack_order: list<string>, font: string, id_size: string, name_size: string, logo: bool}
     */
    public static function defaultOptionsFor(string $sizeKey): array
    {
        $compact = (($preset = self::sizes()[$sizeKey] ?? null)['layout'] ?? '') === 'compact';

        return [
            'qr_side' => 'left',
            'stack_order' => $compact
                ? ['numeric_id', 'name', 'ownership']
                : ['name', 'asset_tag', 'barcode', 'numeric_id', 'ownership'],
            'font' => 'bold',
            'id_size' => 'large',
            'name_size' => 'medium',
            'logo' => false,
        ];
    }

    /**
     * @return list<string>
     */
    public static function stackableFieldsFor(string $sizeKey): array
    {
        return array_values(array_filter(
            array_keys(self::defaultFieldsFor($sizeKey)),
            fn (string $field) => $field !== 'qr'
        ));
    }

    /**
     * Merge a raw row with defaults (fields + options).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeSizeLayout(string $sizeKey, array $row): array
    {
        $fields = self::defaultFieldsFor($sizeKey);
        $opts = self::defaultOptionsFor($sizeKey);
        if ($fields === []) {
            return [];
        }

        foreach ($fields as $field => $default) {
            if (array_key_exists($field, $row)) {
                $fields[$field] = filter_var($row[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $qrSide = $row['qr_side'] ?? $opts['qr_side'];
        $font = $row['font'] ?? $opts['font'];
        $idSize = $row['id_size'] ?? $opts['id_size'];
        $nameSize = $row['name_size'] ?? $opts['name_size'];
        $logo = array_key_exists('logo', $row)
            ? filter_var($row['logo'], FILTER_VALIDATE_BOOLEAN)
            : $opts['logo'];

        $stackable = self::stackableFieldsFor($sizeKey);
        if ($logo) {
            array_unshift($stackable, 'logo');
        }

        $orderIn = $row['stack_order'] ?? $opts['stack_order'];
        if (! is_array($orderIn)) {
            $orderIn = $opts['stack_order'];
        }

        $cleanOrder = [];
        foreach ($orderIn as $key) {
            if (! is_string($key)) {
                continue;
            }
            if ($key === 'logo' && ! $logo) {
                continue;
            }
            if (in_array($key, $stackable, true) && ! in_array($key, $cleanOrder, true)) {
                $cleanOrder[] = $key;
            }
        }
        foreach ($stackable as $key) {
            if (! in_array($key, $cleanOrder, true)) {
                $cleanOrder[] = $key;
            }
        }

        return array_merge($fields, [
            'qr_side' => in_array($qrSide, ['left', 'right'], true) ? $qrSide : 'left',
            'stack_order' => $cleanOrder,
            'font' => in_array($font, ['bold', 'regular'], true) ? $font : 'bold',
            'id_size' => in_array($idSize, ['small', 'medium', 'large'], true) ? $idSize : 'large',
            'name_size' => in_array($nameSize, ['small', 'medium', 'large'], true) ? $nameSize : 'medium',
            'logo' => $logo,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaultLayouts(): array
    {
        $out = [];
        foreach (array_keys(self::sizes()) as $key) {
            $out[$key] = self::normalizeSizeLayout($key, []);
        }

        return $out;
    }

    /**
     * Keys allowed on a size layout row (for validation).
     *
     * @return list<string>
     */
    public static function allowedLayoutKeysFor(string $sizeKey): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::defaultFieldsFor($sizeKey)),
            ['qr_side', 'stack_order', 'font', 'id_size', 'name_size', 'logo'],
        )));
    }

    /** @var array<string, mixed>|null */
    protected ?array $layoutOverride = null;

    /**
     * Merged saved layout + defaults for every size.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allLayouts(): array
    {
        $saved = $this->settings->get('labels', 'layout', []);
        if (! is_array($saved)) {
            $saved = [];
        }

        $out = [];
        foreach (array_keys(self::sizes()) as $sizeKey) {
            $row = is_array($saved[$sizeKey] ?? null) ? $saved[$sizeKey] : [];
            $out[$sizeKey] = self::normalizeSizeLayout($sizeKey, $row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutFor(string $size): array
    {
        $key = $this->resolveSize($size)['key'];

        if ($this->layoutOverride !== null) {
            return self::normalizeSizeLayout($key, $this->layoutOverride);
        }

        return $this->allLayouts()[$key] ?? self::normalizeSizeLayout($key, []);
    }

    public function fieldEnabled(string $size, string $field): bool
    {
        $layout = $this->layoutFor($size);

        return (bool) ($layout[$field] ?? false);
    }

    /**
     * Render a label PNG, optionally with an unsaved layout draft for this size.
     *
     * @param  array<string, mixed>|null  $layoutOverride
     */
    public function composeLabelPng(Item $item, string $size = self::DEFAULT_SIZE, ?array $layoutOverride = null): string
    {
        $preset = $this->resolveSize($size);
        $item->loadMissing('toolType');
        $this->layoutOverride = $layoutOverride;

        try {
            if ($preset['layout'] === 'compact') {
                return $this->composeCompactLabel($item, $preset);
            }

            return $this->composeFullLabel($item, $preset);
        } finally {
            $this->layoutOverride = null;
        }
    }

    public function pngBinary(Item $item, string $size = self::DEFAULT_SIZE, ?array $layoutOverride = null): string
    {
        $preset = $this->resolveSize($size);
        $binary = $this->composeLabelPng($item, $preset['key'], $layoutOverride);
        if ($layoutOverride === null) {
            Storage::disk('public')->put("labels/{$item->asset_tag}-{$preset['key']}.png", $binary);
        }

        return $binary;
    }

    /**
     * @return array<string, array{
     *   key: string,
     *   label: string,
     *   width_mm: float,
     *   height_mm: float,
     *   width_px: int,
     *   height_px: int,
     *   dpi: int,
     *   hint: string,
     *   layout: string
     * }>
     */
    public static function sizes(): array
    {
        return [
            'standard' => [
                'key' => 'standard',
                'label' => 'Standard (large equipment)',
                'width_mm' => 61.0,
                'height_mm' => 30.5,
                'width_px' => 720,
                'height_px' => 360,
                'dpi' => 300,
                'hint' => 'QR + barcode + 6-digit ID — good for sheet printers and big tools',
                'layout' => 'full',
            ],
            'medium' => [
                'key' => 'medium',
                'label' => 'Medium (~50×25 mm)',
                'width_mm' => 50.0,
                'height_mm' => 25.0,
                'width_px' => 590,
                'height_px' => 295,
                'dpi' => 300,
                'hint' => 'Same content as standard, tighter for mid-size stickers',
                'layout' => 'full',
            ],
            'niimbot_15x30' => [
                'key' => 'niimbot_15x30',
                'label' => 'NiimBot 15×30 mm',
                // Physical sticker face: 30mm wide × 15mm tall (landscape on the roll).
                'width_mm' => 30.0,
                'height_mm' => 15.0,
                // D11/D110: printDirection "left" wants canvas width=feed, height=printhead (96).
                'width_px' => 192,
                'height_px' => 96,
                'dpi' => 163,
                'hint' => 'Landscape QR + 6-digit ID for handheld NiimBot 15×30mm labels',
                'layout' => 'compact',
            ],
        ];
    }

    public function resolveSize(?string $size): array
    {
        $key = $size ?: self::DEFAULT_SIZE;
        $sizes = self::sizes();

        if (! isset($sizes[$key])) {
            throw ValidationException::withMessages([
                'size' => 'Unknown label size. Use: '.implode(', ', array_keys($sizes)),
            ]);
        }

        return $sizes[$key];
    }

    /** @return list<array<string, mixed>> */
    public function sizeCatalog(): array
    {
        return array_values(self::sizes());
    }

    public function generatePng(Item $item, string $size = self::DEFAULT_SIZE): string
    {
        $preset = $this->resolveSize($size);
        $binary = $this->composeLabelPng($item, $preset['key']);
        $path = "labels/{$item->asset_tag}-{$preset['key']}.png";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * @param  list<int>|null  $itemIds
     * @return list<int>
     */
    public function resolveItemIds(?array $itemIds, bool $all = false): array
    {
        if ($all) {
            return Item::query()->orderBy('asset_tag')->pluck('id')->all();
        }

        if (empty($itemIds)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $itemIds)));
    }

    public function exportZip(array $itemIds, string $size = self::DEFAULT_SIZE): string
    {
        $preset = $this->resolveSize($size);
        $zipPath = storage_path('app/labels-export-'.time().'.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (Item::query()->whereIn('id', $itemIds)->with('toolType')->orderBy('asset_tag')->get() as $item) {
            $path = $this->generatePng($item, $preset['key']);
            $full = Storage::disk('public')->path($path);
            $zip->addFile($full, $item->asset_tag.'-'.$preset['key'].'.png');
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function exportSheetPdf(array $itemIds, string $size = self::DEFAULT_SIZE): \Barryvdh\DomPDF\PDF
    {
        $preset = $this->resolveSize($size);
        $cols = $preset['layout'] === 'compact' ? 3 : 2;

        $items = Item::query()
            ->with('toolType')
            ->whereIn('id', $itemIds)
            ->orderBy('asset_tag')
            ->get()
            ->map(function (Item $item) use ($preset) {
                $png = $this->pngBinary($item, $preset['key']);

                return [
                    'asset_tag' => $item->asset_tag,
                    'numeric_code' => $item->numeric_code,
                    'label' => $item->displayName(),
                    'tool_type' => $item->toolType?->name,
                    'qr_data_uri' => 'data:image/png;base64,'.base64_encode($png),
                ];
            });

        return Pdf::loadView('exports.qr-sheet', [
            'items' => $items,
            'size' => $preset,
            'cols' => $cols,
        ])->setPaper('letter', 'portrait');
    }

    /**
     * @param  array{key: string, width_px: int, height_px: int}  $preset
     */
    protected function composeFullLabel(Item $item, array $preset): string
    {
        $width = $preset['width_px'];
        $height = $preset['height_px'];
        $scale = $width / 720;
        $cfg = $this->layoutFor($preset['key']);

        $showQr = (bool) ($cfg['qr'] ?? false);
        $qrSide = ($cfg['qr_side'] ?? 'left') === 'right' ? 'right' : 'left';
        $idSize = $cfg['id_size'] ?? 'large';
        $nameSize = $cfg['name_size'] ?? 'medium';
        $stackOrder = is_array($cfg['stack_order'] ?? null) ? $cfg['stack_order'] : [];

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 24, 24, 27);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagerectangle($canvas, 0, 0, $width - 1, $height - 1, $black);

        $pad = (int) round(16 * $scale);
        $qrSize = $showQr ? (int) round(220 * $scale) : 0;
        $gapQr = (int) round(14 * $scale);

        if ($showQr) {
            $qrX = $qrSide === 'right' ? $width - $pad - $qrSize : $pad;
            $qrY = (int) round(28 * $scale);
            $qrBinary = $this->qrPngBinary($item->qr_token, max(80, $qrSize), max(4, (int) round(8 * $scale)));
            $qr = imagecreatefromstring($qrBinary);
            imagecopyresized($canvas, $qr, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        if ($showQr && $qrSide === 'left') {
            $textLeft = $pad + $qrSize + $gapQr;
            $textMaxW = $width - $textLeft - $pad;
        } elseif ($showQr) {
            $textLeft = $pad;
            $textMaxW = $width - $pad - $qrSize - $gapQr - $pad;
        } else {
            $textLeft = $pad;
            $textMaxW = $width - 2 * $pad;
        }

        $numericId = (string) ($item->numeric_code ?: $item->numeric_id);
        $cursorY = (int) round(28 * $scale);
        $gap = (int) round(12 * $scale);
        $ownership = $this->ownershipLine();

        foreach ($stackOrder as $field) {
            if ($field === 'qr') {
                continue;
            }
            if ($field === 'logo') {
                if (! ($cfg['logo'] ?? false)) {
                    continue;
                }
                $logoH = (int) round(48 * $scale);
                if ($this->drawLogo($canvas, $textLeft, $cursorY, min($textMaxW, (int) round(160 * $scale)), $logoH)) {
                    $cursorY += $logoH + $gap;
                }
                continue;
            }
            if (! ($cfg[$field] ?? false)) {
                continue;
            }

            if ($field === 'name') {
                $name = $this->truncate($item->displayName(), (int) round(34 * ($width / 720)));
                $nameScale = match ($nameSize) {
                    'small' => max(1, (int) round(1 * $scale)),
                    'large' => max(2, (int) round(3 * $scale)),
                    default => max(1, (int) round(2 * $scale)),
                };
                $nameFont = match ($nameSize) {
                    'small' => 2,
                    'large' => 5,
                    default => 3,
                };
                $this->drawScaledText($canvas, $name, $textLeft, $cursorY, $nameFont, $nameScale, 24, 24, 27);
                $rowH = match ($nameSize) {
                    'small' => 22,
                    'large' => 52,
                    default => 40,
                };
                $cursorY += (int) round($rowH * $scale) + $gap;
            } elseif ($field === 'asset_tag') {
                $tag = strtoupper((string) $item->asset_tag);
                $this->drawScaledText($canvas, $tag, $textLeft, $cursorY, 2, max(1, (int) round(2 * $scale)), 82, 82, 91);
                $cursorY += (int) round(36 * $scale) + $gap;
            } elseif ($field === 'barcode') {
                $barcodeBinary = $this->barcodePngBinary($numericId);
                if ($barcodeBinary !== null) {
                    $barcode = imagecreatefromstring($barcodeBinary);
                    if ($barcode !== false) {
                        $targetW = min((int) round(440 * $scale), $textMaxW);
                        $targetH = (int) round(72 * $scale);
                        imagecopyresampled(
                            $canvas,
                            $barcode,
                            $textLeft,
                            $cursorY,
                            0,
                            0,
                            max(1, $targetW),
                            max(1, $targetH),
                            imagesx($barcode),
                            imagesy($barcode)
                        );
                        imagedestroy($barcode);
                        $cursorY += $targetH + $gap;
                    }
                }
            } elseif ($field === 'numeric_id') {
                $idScale = match ($idSize) {
                    'small' => max(1, (int) round(2 * $scale)),
                    'medium' => max(2, (int) round(3 * $scale)),
                    default => max(2, (int) round(4 * $scale)),
                };
                $this->drawScaledText($canvas, $numericId, $textLeft, $cursorY, 5, $idScale, 24, 24, 27);
                $cursorY += (int) round(28 * $idScale) + $gap;
            } elseif ($field === 'ownership' && $ownership !== '') {
                $footer = $this->truncate($ownership, (int) round(42 * ($width / 720)));
                $this->drawScaledText($canvas, $footer, $textLeft, $cursorY, 1, max(1, (int) round(2 * $scale)), 113, 113, 122);
                $cursorY += (int) round(28 * $scale) + $gap;
            }
        }

        return $this->canvasToPng($canvas);
    }

    /**
     * Landscape NiimBot label — placement options + reflow.
     *
     * @param  array{key: string, width_px: int, height_px: int}  $preset
     */
    protected function composeCompactLabel(Item $item, array $preset): string
    {
        $width = $preset['width_px'];
        $height = $preset['height_px'];
        $cfg = $this->layoutFor($preset['key']);

        $showQr = (bool) ($cfg['qr'] ?? false);
        $qrSide = ($cfg['qr_side'] ?? 'left') === 'right' ? 'right' : 'left';
        $fontWeight = ($cfg['font'] ?? 'bold') === 'regular' ? 'regular' : 'bold';
        $idSize = $cfg['id_size'] ?? 'large';
        $nameSize = $cfg['name_size'] ?? 'medium';
        $stackOrder = is_array($cfg['stack_order'] ?? null) ? $cfg['stack_order'] : [];
        $showLogo = (bool) ($cfg['logo'] ?? false);

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        $black = imagecolorallocate($canvas, 0, 0, 0);

        $qrSize = $showQr ? $height : 0;
        $textPad = 4;
        $textLeft = $textPad;
        $textMaxW = max(1, $width - 2 * $textPad);

        if ($showQr) {
            $qrBinary = $this->qrPngBinary($item->qr_token, $qrSize, 0);
            $qr = imagecreatefromstring($qrBinary);
            $qrX = $qrSide === 'right' ? $width - $qrSize : 0;
            imagecopyresized($canvas, $qr, $qrX, 0, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
            imagedestroy($qr);

            if ($qrSide === 'left') {
                $textLeft = $qrSize + $textPad;
                $textMaxW = max(1, $width - $textLeft - $textPad);
            } else {
                $textLeft = $textPad;
                $textMaxW = max(1, $width - $qrSize - 2 * $textPad);
            }
        }

        $numericId = (string) ($item->numeric_code ?: $item->numeric_id);
        $ownership = $this->ownershipLine();

        $blocks = [];
        foreach ($stackOrder as $field) {
            if ($field === 'logo' && $showLogo) {
                $blocks[] = 'logo';
            } elseif ($field === 'numeric_id' && ($cfg['numeric_id'] ?? false)) {
                $blocks[] = 'id';
            } elseif ($field === 'name' && ($cfg['name'] ?? false)) {
                $blocks[] = 'name';
            } elseif ($field === 'ownership' && ($cfg['ownership'] ?? false) && $ownership !== '') {
                $blocks[] = 'footer';
            }
        }

        $heights = $this->allocateCompactHeights($blocks, $height, $idSize, $nameSize);
        $primaryFont = $this->labelFontPath($fontWeight) ?? $this->labelFontPath('bold');
        $secondaryFont = $this->labelFontPath('regular') ?? $primaryFont;
        // Caps keep short names from ballooning to fill the row (especially "small").
        $nameCap = match ($nameSize) {
            'small' => 8.0,
            'large' => 22.0,
            default => 12.0,
        };
        $nameMinSize = match ($nameSize) {
            'small' => 6.0,
            'large' => 10.0,
            default => 8.0,
        };
        $y = 0;

        foreach ($blocks as $block) {
            $h = $heights[$block] ?? 0;
            if ($h < 4) {
                continue;
            }

            if ($block === 'logo') {
                $this->drawLogo($canvas, $textLeft, $y, $textMaxW, $h);
            } elseif ($block === 'id') {
                if ($primaryFont !== null && function_exists('imagettftext')) {
                    $this->drawTrueTypeFitted($canvas, $numericId, $textLeft, $y, $textMaxW, $h, $primaryFont, 'center', 'middle');
                } else {
                    $this->drawHardTextSized($canvas, $numericId, $textLeft, $y, 5, $textMaxW, $h, true);
                }
            } elseif ($block === 'name') {
                $font = $primaryFont ?? $secondaryFont;
                // Draw into a height-limited band so Small/Medium stay visually smaller than Large.
                $nameBandH = match ($nameSize) {
                    'small' => min($h, (int) max(10, round($height * 0.12))),
                    'large' => $h,
                    default => min($h, (int) max(14, round($height * 0.18))),
                };
                $nameY = $y + (int) floor(($h - $nameBandH) / 2);
                if ($font !== null && function_exists('imagettftext')) {
                    $name = $this->fitTrueTypeText($item->displayName(), $font, $textMaxW, $nameBandH, $nameMinSize);
                    $this->drawTrueTypeFitted(
                        $canvas,
                        $name,
                        $textLeft,
                        $nameY,
                        $textMaxW,
                        $nameBandH,
                        $font,
                        'center',
                        'middle',
                        $nameCap,
                    );
                } else {
                    $name = $this->truncate($item->displayName(), min(20, max(10, intdiv($textMaxW, 4))));
                    $this->drawHardTextSized($canvas, $name, $textLeft, $nameY, 1, $textMaxW, $nameBandH, true);
                }
            } elseif ($block === 'footer') {
                $font = $secondaryFont ?? $primaryFont;
                if ($font !== null && function_exists('imagettftext')) {
                    $footerText = $this->fitTrueTypeText($ownership, $font, $textMaxW, $h, 7.0);
                    $this->drawTrueTypeFitted($canvas, $footerText, $textLeft, $y, $textMaxW, $h, $font, 'center', 'middle');
                } else {
                    $this->drawHardText($canvas, $this->truncate($ownership, 22), $textLeft, $y, 1, 1, $black);
                }
            }

            $y += $h + 1;
        }

        return $this->canvasToPng($this->binarizeCanvas($canvas));
    }

    /**
     * @param  list<string>  $blocks
     * @return array<string, int>
     */
    protected function allocateCompactHeights(
        array $blocks,
        int $height,
        string $idSize = 'large',
        string $nameSize = 'medium',
    ): array {
        $n = count($blocks);
        if ($n === 0) {
            return [];
        }

        $gapTotal = max(0, $n - 1);
        $avail = max($n, $height - $gapTotal);
        $out = [];

        if ($n === 1) {
            $out[$blocks[0]] = $avail;

            return $out;
        }

        $idShare = match ($idSize) {
            'small' => 0.40,
            'medium' => 0.52,
            default => 0.65,
        };

        $nameShare = match ($nameSize) {
            'small' => 0.12,
            'large' => 0.40,
            default => 0.22,
        };

        $weights = [];
        foreach ($blocks as $block) {
            $weights[$block] = match ($block) {
                'id' => $idShare,
                'logo' => 0.18,
                'footer' => 0.14,
                'name' => $nameShare,
                default => 0.2,
            };
        }

        $sum = array_sum($weights) ?: 1.0;
        $used = 0;
        $last = array_key_last($weights);
        foreach ($weights as $block => $w) {
            if ($block === $last) {
                $out[$block] = max(4, $avail - $used);
            } else {
                $h = max(4, (int) floor($avail * ($w / $sum)));
                $out[$block] = $h;
                $used += $h;
            }
        }

        return $out;
    }

    /**
     * Draw branding logo into a box. Returns false if unavailable.
     */
    protected function drawLogo(\GdImage $canvas, int $x, int $y, int $boxW, int $boxH): bool
    {
        $path = trim((string) $this->settings->get('branding', 'logo_path', ''));
        if ($path === '') {
            return false;
        }

        $relative = ltrim(preg_replace('#^/?storage/#', '', $path) ?? $path, '/');
        $candidates = [
            storage_path('app/public/'.$relative),
            public_path($relative),
            public_path('storage/'.$relative),
            public_path(ltrim($path, '/')),
        ];

        $img = false;
        foreach ($candidates as $file) {
            if (! is_readable($file)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $img = @imagecreatefromstring($raw);
            if ($img !== false) {
                break;
            }
        }

        if ($img === false) {
            return false;
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $scale = min($boxW / max(1, $srcW), $boxH / max(1, $srcH));
        $drawW = max(1, (int) floor($srcW * $scale));
        $drawH = max(1, (int) floor($srcH * $scale));
        $dx = $x + (int) floor(($boxW - $drawW) / 2);
        $dy = $y + (int) floor(($boxH - $drawH) / 2);
        imagecopyresampled($canvas, $img, $dx, $dy, 0, 0, $drawW, $drawH, $srcW, $srcH);
        imagedestroy($img);

        return true;
    }
    protected function canvasToPng(\GdImage $canvas): string
    {
        ob_start();
        imagepng($canvas);
        $binary = ob_get_clean();
        imagedestroy($canvas);

        return $binary ?: '';
    }

    protected function qrPngBinary(string $data, int $size, int $margin): string
    {
        $qrCode = new QrCode(
            data: $data,
            size: $size,
            margin: $margin,
        );

        return (new PngWriter)->write($qrCode)->getString();
    }

    protected function barcodePngBinary(string $payload): ?string
    {
        try {
            $generator = new BarcodeGeneratorPNG;

            return $generator->getBarcode($payload, $generator::TYPE_CODE_128, 2, 60);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function drawScaledText(
        \GdImage $canvas,
        string $text,
        int $x,
        int $y,
        int $font,
        int $scale,
        int $r,
        int $g,
        int $b,
    ): void {
        $ink = imagecolorallocate($canvas, $r, $g, $b);
        $this->drawHardText($canvas, $text, $x, $y, $font, $scale, $ink);
    }

    /**
     * Bundled Roboto (Apache 2.0) for thermal labels — falls back to common system bold/regular.
     */
    protected function labelFontPath(string $weight = 'bold'): ?string
    {
        $candidates = match ($weight) {
            'regular' => [
                resource_path('fonts/Roboto-Regular.ttf'),
                resource_path('fonts/DejaVuSans.ttf'),
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\segoeui.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
            ],
            default => [
                resource_path('fonts/Roboto-Bold.ttf'),
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\segoeuib.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            ],
        };

        foreach ($candidates as $path) {
            if (is_string($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} minX, minY, width, height
     */
    protected function trueTypeMetrics(float $size, string $fontPath, string $text): array
    {
        $bbox = imagettfbbox($size, 0, $fontPath, $text);
        if ($bbox === false) {
            return [0, 0, 1, 1];
        }

        $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
        $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
        $minX = (int) min($xs);
        $maxX = (int) max($xs);
        $minY = (int) min($ys);
        $maxY = (int) max($ys);

        return [$minX, $minY, max(1, $maxX - $minX), max(1, $maxY - $minY)];
    }

    /**
     * Largest TrueType size that fits in the box, preserving aspect ratio (no stretch).
     *
     * @param  'left'|'center'  $alignX
     * @param  'top'|'middle'|'bottom'  $alignY
     */
    protected function drawTrueTypeFitted(
        \GdImage $canvas,
        string $text,
        int $x,
        int $y,
        int $boxW,
        int $boxH,
        string $fontPath,
        string $alignX = 'left',
        string $alignY = 'middle',
        ?float $maxSize = null,
    ): void {
        $text = trim($text);
        if ($text === '' || $boxW < 4 || $boxH < 4) {
            return;
        }

        $best = $this->maxTrueTypeSize($text, $fontPath, $boxW, $boxH, $maxSize);
        [$minX, $minY, $tw, $th] = $this->trueTypeMetrics($best, $fontPath, $text);
        $drawX = match ($alignX) {
            'center' => $x + (int) floor(($boxW - $tw) / 2) - $minX,
            default => $x - $minX,
        };
        $drawY = match ($alignY) {
            'top' => $y - $minY,
            'bottom' => $y + $boxH - $th - $minY,
            default => $y + (int) floor(($boxH - $th) / 2) - $minY,
        };

        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagettftext($canvas, $best, 0, $drawX, $drawY, $black, $fontPath, $text);
    }

    protected function maxTrueTypeSize(
        string $text,
        string $fontPath,
        int $boxW,
        int $boxH,
        ?float $maxSize = null,
    ): float {
        $lo = 5.0;
        $ceiling = (float) max(6, $boxH + 4);
        if ($maxSize !== null) {
            $ceiling = min($ceiling, max(5.0, $maxSize));
        }
        $hi = $ceiling;
        $best = 5.0;

        for ($i = 0; $i < 16; $i++) {
            $mid = ($lo + $hi) / 2;
            [, , $tw, $th] = $this->trueTypeMetrics($mid, $fontPath, $text);
            if ($tw <= $boxW && $th <= $boxH) {
                $best = $mid;
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return min($best, $ceiling);
    }

    /**
     * Longest text that still renders at least $minSize (falls back to best-fit truncation).
     */
    protected function fitTrueTypeText(
        string $text,
        string $fontPath,
        int $boxW,
        int $boxH,
        float $minSize = 10.0,
    ): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $fullLen = mb_strlen($text);
        $fallback = $text;

        for ($len = $fullLen; $len >= 1; $len--) {
            $candidate = $len === $fullLen
                ? $text
                : rtrim(mb_substr($text, 0, $len)).'...';
            $size = $this->maxTrueTypeSize($candidate, $fontPath, $boxW, $boxH);
            $fallback = $candidate;
            if ($size >= $minSize) {
                return $candidate;
            }
        }

        return $fallback;
    }

    /**
     * Crisp nearest-neighbour text — required for 1-bit thermal printers.
     * Optional $scaleY lets digits grow taller than they are wide.
     */
    protected function drawHardText(
        \GdImage $canvas,
        string $text,
        int $x,
        int $y,
        int $font,
        int $scale,
        int $_color,
        ?int $scaleY = null,
    ): void {
        $sx = max(1, $scale);
        $sy = max(1, $scaleY ?? $scale);
        $pad = 1;
        $srcW = imagefontwidth($font) * strlen($text) + $pad * 2;
        $srcH = imagefontheight($font) + $pad * 2;
        $this->drawHardTextSized($canvas, $text, $x, $y, $font, $srcW * $sx, $srcH * $sy);
    }

    /**
     * Draw bitmap text stretched to an exact destination box (nearest-neighbour).
     * When $cropGlyphs is true, empty padding around the ink is removed before scaling
     * so glyphs fill the box edge-to-edge.
     */
    protected function drawHardTextSized(
        \GdImage $canvas,
        string $text,
        int $x,
        int $y,
        int $font,
        int $destW,
        int $destH,
        bool $cropGlyphs = false,
    ): void {
        $pad = 1;
        $srcW = imagefontwidth($font) * strlen($text) + $pad * 2;
        $srcH = imagefontheight($font) + $pad * 2;
        $tmp = imagecreatetruecolor(max(1, $srcW), max(1, $srcH));
        $bg = imagecolorallocate($tmp, 255, 255, 255);
        imagefilledrectangle($tmp, 0, 0, $srcW, $srcH, $bg);
        $ink = imagecolorallocate($tmp, 0, 0, 0);
        imagestring($tmp, $font, $pad, $pad, $text, $ink);

        $copy = $tmp;
        $cx = 0;
        $cy = 0;
        $cw = $srcW;
        $ch = $srcH;

        if ($cropGlyphs) {
            $minX = $srcW;
            $minY = $srcH;
            $maxX = -1;
            $maxY = -1;
            for ($py = 0; $py < $srcH; $py++) {
                for ($px = 0; $px < $srcW; $px++) {
                    $rgb = imagecolorat($tmp, $px, $py);
                    $sum = (($rgb >> 16) & 255) + (($rgb >> 8) & 255) + ($rgb & 255);
                    if ($sum < 600) {
                        $minX = min($minX, $px);
                        $minY = min($minY, $py);
                        $maxX = max($maxX, $px);
                        $maxY = max($maxY, $py);
                    }
                }
            }
            if ($maxX >= $minX && $maxY >= $minY) {
                $cx = $minX;
                $cy = $minY;
                $cw = $maxX - $minX + 1;
                $ch = $maxY - $minY + 1;
            }
        }

        imagecopyresized(
            $canvas,
            $copy,
            $x,
            $y,
            $cx,
            $cy,
            max(1, $destW),
            max(1, $destH),
            max(1, $cw),
            max(1, $ch)
        );
        imagedestroy($tmp);
    }

    /**
     * Force every pixel to pure #000 or #FFF (NiimBot treats any grey as black).
     */
    protected function binarizeCanvas(\GdImage $canvas): \GdImage
    {
        $w = imagesx($canvas);
        $h = imagesy($canvas);
        $out = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefilledrectangle($out, 0, 0, $w, $h, $white);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (($r + $g + $b) < 600) {
                    imagesetpixel($out, $x, $y, $black);
                }
            }
        }

        imagedestroy($canvas);

        return $out;
    }

    protected function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, max(1, $max - 3)).'...';
    }

    /**
     * Find an item by QR token, asset tag, or exact 6-digit tool number.
     */
    public static function findItemByScanCode(string $code): ?Item
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if (preg_match('/^\d{6}$/', $code) === 1) {
            $byCode = Item::query()->where('numeric_code', $code)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        return Item::query()
            ->where(function ($q) use ($code) {
                $q->where('qr_token', strtolower($code))
                    ->orWhere('asset_tag', $code)
                    ->orWhereRaw('LOWER(asset_tag) = ?', [strtolower($code)]);
            })
            ->first();
    }

    /** @return Collection<int, Item> */
    public function itemsForExport(array $itemIds): Collection
    {
        return Item::query()->with('toolType')->whereIn('id', $itemIds)->orderBy('asset_tag')->get();
    }
}
