@extends('layouts.app')
@section('title', $definition['title'])
@section('subheading', $definition['description'])

@section('actions')
    <div class="btn-group">
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary">All reports</a>
        @if ($canExport)
            <a href="{{ route('reports.export', ['report' => $key, 'format' => 'csv']) }}?{{ http_build_query($filters) }}"
               class="btn btn-sm btn-outline-success"><i class="bi bi-filetype-csv"></i> Excel / CSV</a>
            <a href="{{ route('reports.export', ['report' => $key, 'format' => 'pdf']) }}?{{ http_build_query($filters) }}"
               class="btn btn-sm btn-outline-danger"><i class="bi bi-filetype-pdf"></i> PDF</a>
        @endif
    </div>
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected(($filters['property_id'] ?? null) == $property->id)>{{ $property->reference }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Investor</label>
                <select name="investor_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($investors as $investor)
                        <option value="{{ $investor->id }}" @selected(($filters['investor_id'] ?? null) == $investor->id)>{{ $investor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Apply</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        @foreach ($columns as $column)
                            <th class="{{ ($column['align'] ?? '') === 'money' ? 'text-end' : '' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            @php $value = $row[$column['key']] ?? null; @endphp
                            @if (($column['align'] ?? '') === 'money')
                                <td class="text-end {{ money_class($value ?? 0) }}">{{ $value === null ? '-' : pkr($value, false) }}</td>
                            @else
                                <td>{{ $value === null || $value === '' ? '-' : $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="empty-state">No data for this filter.</td></tr>
                @endforelse
                </tbody>
                @if ($totals !== [] && count($rows) > 0)
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            @foreach ($columns as $index => $column)
                                @if ($index === 0)
                                    <td>Total</td>
                                @elseif (array_key_exists($column['key'], $totals))
                                    <td class="text-end {{ money_class($totals[$column['key']]) }}">{{ pkr($totals[$column['key']], false) }}</td>
                                @else
                                    <td></td>
                                @endif
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @unless ($canExport)
        <div class="text-muted small mt-2">
            Export is not available: it is either not enabled on your plan or not included in your permissions.
        </div>
    @endunless
@endsection
