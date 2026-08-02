<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Equipment labels</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #18181b; margin: 0; }
        h1 { font-size: 13px; margin: 0 0 4px; }
        .hint { color: #71717a; margin: 0 0 10px; font-size: 9px; }
        .grid { width: 100%; border-collapse: collapse; }
        .cell {
            width: {{ 100 / max(1, $cols) }}%;
            vertical-align: middle;
            border: 1px dashed #a1a1aa;
            padding: 4px;
            text-align: center;
        }
        .cell img {
            width: 100%;
            max-width: {{ ($size['layout'] ?? '') === 'compact' ? '180px' : '340px' }};
            height: auto;
        }
    </style>
</head>
<body>
    <h1>Depot Borrow — {{ $size['label'] ?? 'labels' }} ({{ $items->count() }})</h1>
    <p class="hint">
        {{ $size['hint'] ?? 'QR, barcode, and typed ID.' }}
        @if(($size['layout'] ?? '') === 'compact')
            Compact stickers for NiimBot-style printers — feed the PNG ZIP or use Print to NiimBot in the app.
        @else
            Cut on the dashed lines or feed the PNG ZIP into a label printer.
        @endif
    </p>
    <table class="grid">
        @foreach ($items->chunk($cols) as $row)
            <tr>
                @foreach ($row as $item)
                    <td class="cell">
                        <img src="{{ $item['qr_data_uri'] }}" alt="{{ $item['asset_tag'] }}">
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < $cols; $i++)
                    <td class="cell"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
