<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1e293b; }
        .brand { font-size: 14pt; font-weight: bold; color: #2563eb; margin-bottom: 4px; }
        .title { font-size: 13pt; font-weight: bold; margin-bottom: 2px; }
        .subtitle { font-size: 10pt; color: #64748b; margin-bottom: 12px; }
        .meta { font-size: 9pt; color: #475569; margin-bottom: 16px; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; }
        th {
            background: #3c8dbc;
            color: #fff;
            font-weight: bold;
            font-size: 9pt;
            border: 1px solid #2e6f99;
            padding: 8px 10px;
            text-align: center;
            vertical-align: middle;
        }
        th.num { text-align: right; }
        td {
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 10pt;
        }
        td.num { text-align: right; }
        td.center { text-align: center; }
        tr:nth-child(even) td { background: #f8fafc; }
        tr.total td { background: #fff8e8; font-weight: bold; }
        .pct-good { background: #d1fae5; color: #047857; }
        .pct-warn { background: #fef3c7; color: #b45309; }
        .pct-bad { background: #ffe4e6; color: #be123c; }
        .currency { color: #047857; }
    </style>
</head>
<body>
    @if(!empty($meta['brand']))
        <div class="brand">{{ $meta['brand'] }}</div>
    @endif
    <div class="title">{{ $meta['title'] ?? 'Report' }}</div>
    @if(!empty($meta['subtitle']))
        <div class="subtitle">{{ $meta['subtitle'] }}</div>
    @endif
    <div class="meta">
        @if(!empty($meta['date_from']) && !empty($meta['date_to']))
            {{ $meta['period_label'] ?? 'Period' }}: {{ $meta['date_from'] }} — {{ $meta['date_to'] }}<br>
        @endif
        {{ $meta['generated_label'] ?? 'Generated' }}: {{ $meta['generated_at'] ?? now()->format('Y-m-d H:i') }}
    </div>
    <table>
        <thead>
            @php
                $headerRows = $meta['header_rows'] ?? null;
            @endphp
            @if(is_array($headerRows) && count($headerRows) > 0)
                @foreach($headerRows as $headerRow)
                    <tr>
                        @foreach($headerRow as $cell)
                            <th
                                @if(!empty($cell['colspan'])) colspan="{{ (int) $cell['colspan'] }}" @endif
                                @if(!empty($cell['rowspan'])) rowspan="{{ (int) $cell['rowspan'] }}" @endif
                            >{{ $cell['label'] ?? '' }}</th>
                        @endforeach
                    </tr>
                @endforeach
            @else
                <tr>
                    @foreach($columns as $col)
                        <th class="{{ ($col['format'] ?? 'text') !== 'text' ? 'num' : '' }}">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ !empty($row['_is_total']) ? 'total' : '' }}">
                    @foreach($columns as $col)
                        @php
                            $val = $row[$col['key']] ?? '';
                            $fmt = $col['format'] ?? 'text';
                            $cls = ($fmt !== 'text') ? 'num' : 'center';
                            if ($fmt === 'currency') $cls .= ' currency';
                            if ($fmt === 'percent' && is_numeric($val)) {
                                $v = (float) $val;
                                $cls .= ' ' . ($v >= 50 ? 'pct-good' : ($v >= 25 ? 'pct-warn' : 'pct-bad'));
                            }
                        @endphp
                        <td class="{{ trim($cls) }}">{{ $val }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
