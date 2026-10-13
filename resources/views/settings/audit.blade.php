@extends('layouts.app')
@section('title', 'Audit log')
@section('subheading', 'Who did what, when and why. Corrections, cancellations, reversals, approvals, exports and document actions all appear here.')

@section('actions')
    <a href="{{ route('settings.index') }}" class="btn btn-sm btn-outline-secondary">Back to settings</a>
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Action starts with</label>
                <input name="action" value="{{ request('action') }}" class="form-control form-control-sm" placeholder="sale, expense, approval">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ pms_date($log->created_at, 'd M Y H:i') }}</td>
                        <td class="small">{{ $log->user?->name ?? 'System' }}</td>
                        <td><code class="small">{{ $log->action }}</code></td>
                        <td class="small">{{ $log->description }}</td>
                        <td class="small text-muted">{{ $log->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Nothing recorded for this filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
