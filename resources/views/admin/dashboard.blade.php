@extends('layouts.app')
@section('title', 'Platform')
@section('subheading', 'Operational overview. Customer financial data is deliberately not shown here.')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Accounts</div><div class="kpi-value">{{ $totalAccounts }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Awaiting approval</div>
            <div class="kpi-value text-warning">{{ $byStatus['pending_approval'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Active</div><div class="kpi-value text-success">{{ $byStatus['active'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Suspended</div><div class="kpi-value">{{ $byStatus['suspended'] ?? 0 }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white fw-semibold">Accounts waiting for activation</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Business</th><th>Contact</th><th>Requested</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($pendingAccounts as $tenant)
                            <tr>
                                <td class="fw-semibold">{{ $tenant->name }}</td>
                                <td class="small">{{ $tenant->contact_email }}</td>
                                <td class="small">{{ pms_date($tenant->created_at) }}</td>
                                <td class="text-end"><a href="{{ route('admin.accounts.show', $tenant) }}" class="btn btn-sm btn-primary">Review</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Nothing waiting.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Platform health</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-7">Workspace users</dt><dd class="col-5 text-end">{{ $totalUsers }}</dd>
                        <dt class="col-7">Properties across all accounts</dt><dd class="col-5 text-end">{{ $totalProperties }}</dd>
                        <dt class="col-7">Last backup</dt>
                        <dd class="col-5 text-end">
                            @if ($lastBackup)
                                <span class="badge {{ $lastBackup->status === 'success' ? 'bg-success' : 'bg-danger' }}">
                                    {{ ucfirst($lastBackup->status) }}
                                </span>
                                <div class="text-muted" style="font-size:.75rem">{{ pms_date($lastBackup->started_at, 'd M H:i') }}</div>
                            @else
                                <span class="text-muted">Never run</span>
                            @endif
                        </dd>
                        <dt class="col-7">Failed backups (7 days)</dt>
                        <dd class="col-5 text-end {{ $failedBackups > 0 ? 'text-danger fw-semibold' : '' }}">{{ $failedBackups }}</dd>
                    </dl>
                    <a href="{{ route('admin.backups.index') }}" class="btn btn-sm btn-outline-secondary mt-3">Open backups</a>
                </div>
            </div>

            <div class="card">
                <div class="card-body small text-muted">
                    <strong>Note on access.</strong> You can see counts and account status, but not customer
                    properties, money or reports. To look inside a workspace you must start a support session with a
                    written reason; it expires automatically and every page you open is written to that customer
                    audit log.
                </div>
            </div>
        </div>
    </div>
@endsection
