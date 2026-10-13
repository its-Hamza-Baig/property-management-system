@extends('layouts.app')
@section('title', 'Customer accounts')

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Business name or email">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ([
                        'pending_verification' => 'Awaiting email verification',
                        'pending_approval' => 'Awaiting approval',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                        'closed' => 'Closed',
                    ] as $v => $l)
                        <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Business</th><th>Contact</th><th>Plan</th><th>Users</th><th>Status</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td><a href="{{ route('admin.accounts.show', $tenant) }}" class="fw-semibold">{{ $tenant->name }}</a></td>
                        <td class="small">{{ $tenant->contact_email }}</td>
                        <td>{{ $tenant->plan?->name ?? '-' }}</td>
                        <td>{{ $tenant->users_count }}</td>
                        <td>
                            <span class="badge {{ match ($tenant->status) {
                                'active' => 'bg-success',
                                'pending_approval' => 'bg-warning text-dark',
                                'suspended', 'rejected' => 'bg-danger',
                                default => 'bg-secondary',
                            } }}">{{ $tenant->statusLabel() }}</span>
                        </td>
                        <td class="small">{{ pms_date($tenant->created_at) }}</td>
                        <td class="text-end"><a href="{{ route('admin.accounts.show', $tenant) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No accounts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $tenants->links() }}</div>
@endsection
