<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $definition['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#222; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .meta { color:#666; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; }
        th { background: #f0f2f5; text-align: left; font-size: 9px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #f7f8fa; }
        .footer { margin-top: 12px; color:#777; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $definition['title'] }}</h1>
    <div class="meta">
        {{ $tenant?->name }} &middot; Generated {{ now()->format('d M Y H:i') }}
        @if (! empty($filters['from']) || ! empty($filters['to']))
            &middot; Period {{ $filters['from'] ?? 'start' }} to {{ $filters['to'] ?? 'today' }}
        @endif
        <br>{{ $definition['description'] }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ ($column['align'] ?? '') === 'money' ? 'num' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                    @php $value = $row[$column['key']] ?? null; @endphp
                    @if (($column['align'] ?? '') === 'money')
                        <td class="num">{{ $value === null ? '-' : pkr($value, false) }}</td>
                    @else
                        <td>{{ $value === null || $value === '' ? '-' : $value }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}">No data for this filter.</td></tr>
        @endforelse
        </tbody>
        @if ($totals !== [] && count($rows) > 0)
            <tfoot>
                <tr>
                    @foreach ($columns as $index => $column)
                        @if ($index === 0)
                            <td>Total</td>
                        @elseif (array_key_exists($column['key'], $totals))
                            <td class="num">{{ pkr($totals[$column['key']], false) }}</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        All amounts in PKR. Realised profit is an accounting figure and is not the same as cash received.
    </div>
</body>
</html>
