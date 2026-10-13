@extends('layouts.app')
@section('title', 'Properties')
@section('subheading', $activeCount.' active in inventory'.((int) $limit === -1 ? '' : ' of '.$limit.' allowed on your plan').'. Sold properties do not count.')

@section('actions')
    @can('create', App\Models\Property::class)
        <a href="{{ route('properties.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add property</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                           placeholder="Reference, title, society, plot or city">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach (['available' => 'Available', 'reserved' => 'Reserved', 'under_sale' => 'Under sale', 'sold' => 'Sold'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach (App\Models\Property::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">City</label>
                    <input type="text" name="city" value="{{ request('city') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Property</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Total cost</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($properties as $property)
                    <tr>
                        <td><a href="{{ route('properties.show', $property) }}" class="fw-semibold">{{ $property->reference }}</a></td>
                        <td>
                            {{ $property->title }}
                            @if ($property->project_name)
                                <div class="text-muted small">{{ $property->project_name }}{{ $property->block ? ', '.$property->block : '' }}{{ $property->plot_number ? ', plot '.$property->plot_number : '' }}</div>
                            @endif
                        </td>
                        <td>{{ $property->city ?: '-' }}</td>
                        <td>{{ $property->typeLabel() }}</td>
                        <td><span class="badge {{ $property->statusBadgeClass() }}">{{ $property->statusLabel() }}</span></td>
                        <td class="text-end money">{{ pkr($property->totalCost(), false) }}</td>
                        <td class="text-end">
                            <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">
                        No properties yet. <a href="{{ route('properties.create') }}">Add your first one</a>.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $properties->links() }}</div>
@endsection
