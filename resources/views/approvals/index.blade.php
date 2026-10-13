@extends('layouts.app')
@section('title', 'Approvals')
@section('subheading', 'Only the actions you switched on in Settings appear here. Nobody can approve their own request.')

@section('content')
    <ul class="nav nav-tabs mb-3">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $value ? 'active' : '' }}" href="{{ route('approvals.index', ['status' => $value]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Raised</th><th>Action</th><th>Requested by</th><th class="text-end">Amount</th><th>Decision</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $request)
                    <tr>
                        <td>{{ pms_date($request->created_at) }}</td>
                        <td>{{ $request->actionLabel() }}</td>
                        <td>{{ $request->requester?->name }}</td>
                        <td class="text-end money">{{ pkr($request->amount, false) }}</td>
                        <td>
                            <span class="badge {{ $request->statusBadgeClass() }}">{{ $request->statusLabel() }}</span>
                            @if ($request->approver)
                                <div class="text-muted small">{{ $request->approver->name }}, {{ pms_date($request->decided_at) }}</div>
                            @endif
                        </td>
                        <td class="text-end"><a href="{{ route('approvals.show', $request) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
