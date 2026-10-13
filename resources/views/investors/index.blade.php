@extends('layouts.app')
@section('title', 'Investors')
@section('subheading', 'Investors are never deleted. Deactivation keeps every historical record attached.')

@section('actions')
    @can('create', App\Models\Investor::class)
        <a href="{{ route('investors.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add investor</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, phone or CNIC">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Contact</th><th>Active investments</th><th>Portal</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($investors as $investor)
                    <tr>
                        <td><a href="{{ route('investors.show', $investor) }}" class="fw-semibold">{{ $investor->name }}</a></td>
                        <td class="small">
                            {{ $investor->phone ?: '-' }}
                            @if ($investor->email)<div class="text-muted">{{ $investor->email }}</div>@endif
                        </td>
                        <td>{{ $investor->active_allocations_count }}</td>
                        <td>
                            @if ($investor->portal_enabled)
                                <span class="badge bg-success">Enabled</span>
                            @else
                                <span class="text-muted small">Off</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $investor->isActive() ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($investor->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('investors.show', $investor) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No investors yet. <a href="{{ route('investors.create') }}">Add the first one</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $investors->links() }}</div>
@endsection
