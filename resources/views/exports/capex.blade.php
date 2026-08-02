<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Capital Expenditure Forecast</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
        }
        .header {
            display: flex;
            border-bottom: 3px solid #0f766e;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #0f172a;
        }
        .header .app-name {
            color: #0f766e;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .header .meta {
            margin-top: 6px;
            color: #6b7280;
            font-size: 10px;
        }
        .summary {
            display: flex;
            margin-bottom: 18px;
        }
        .summary .card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            margin-right: 10px;
            background-color: #f8fafc;
        }
        .summary .card .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.4px;
        }
        .summary .card .value {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .year-section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .year-title {
            background-color: #0f766e;
            color: #ffffff;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 12px;
            border-radius: 4px 4px 0 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead th {
            background-color: #f1f5f9;
            text-align: left;
            padding: 6px 8px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom: 1px solid #cbd5e1;
        }
        table tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #eef2f7;
            font-size: 10px;
        }
        table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .text-right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .badge-eol {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .badge-ok {
            background-color: #dcfce7;
            color: #15803d;
        }
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="app-name">{{ $appName }}</div>
            <h1>Capital Expenditure Forecast</h1>
            <div class="meta">Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="card">
            <div class="label">Total Assets</div>
            <div class="value">{{ $rows->count() }}</div>
        </div>
        <div class="card">
            <div class="label">Total Replacement Cost</div>
            <div class="value">${{ number_format($rows->sum('replacement_cost'), 2) }}</div>
        </div>
        <div class="card">
            <div class="label">End-of-Life Soon</div>
            <div class="value">{{ $rows->where('end_of_life_soon', 'Yes')->count() }}</div>
        </div>
        <div class="card">
            <div class="label">Planning Years</div>
            <div class="value">{{ $byYear->keys()->count() }}</div>
        </div>
    </div>

    @foreach($byYear as $year => $items)
        <div class="year-section">
            <div class="year-title">{{ $year }} &mdash; {{ $items->count() }} asset(s) &mdash; ${{ number_format($items->sum('replacement_cost'), 2) }}</div>
            <table>
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Tool Type</th>
                        <th>Depot</th>
                        <th>Purchased</th>
                        <th class="text-right">Purchase Price</th>
                        <th class="text-right">Net Cost</th>
                        <th class="text-right">Lifespan</th>
                        <th>Condition</th>
                        <th>Reasons</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $row)
                        <tr>
                            <td>{{ $row['asset_tag'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td>{{ $row['tool_type'] }}</td>
                            <td>{{ $row['depot'] }}</td>
                            <td>{{ $row['purchase_date'] ?? '&mdash;' }}</td>
                            <td class="text-right">${{ number_format($row['purchase_price'], 2) }}</td>
                            <td class="text-right">${{ number_format($row['net_replacement_cost'] ?? $row['replacement_cost'], 2) }}</td>
                            <td class="text-right">{{ $row['lifespan_years'] }} yrs</td>
                            <td>{{ ucfirst($row['condition'] ?? '') }}</td>
                            <td>{{ $row['suggest_replace_reason'] ?? 'age' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">
        {{ $appName }} &middot; Depot Borrow Platform &middot; Confidential capital planning document
    </div>
</body>
</html>
