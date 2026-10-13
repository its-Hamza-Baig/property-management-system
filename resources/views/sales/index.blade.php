@extends('layouts.app')
@section('title', 'Sales')
@section('subheading', 'Realised profit on finalised sales: '.pkr($realizedProfit).'. This is an accounting figure, not cash in hand.')

@section('actions')
    @can('create', App\Models\Sale::class)
        <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create sale</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Search property</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'approved' => 'Approved', 'finalized' => 'Finalised', 'cancelled' => 'Cancelled'] as $v => $l)
                        <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
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
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Property</th><th>Buyer</th><th>Status</th>
                        <th class="text-end">Sale value</th><th class="text-end">Received</th>
                        <th class="text-end">Outstanding</th><th class="text-end">Realised profit</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>{{ pms_date($sale->agreement_date) }}</td>
                        <td><a href="{{ route('sales.show', $sale) }}" class="fw-semibold">{{ $sale->property->reference }}</a></td>
                        <td>{{ $sale->buyer->name }}</td>
                        <td><span class="badge {{ $sale->statusBadgeClass() }}">{{ $sale->statusLabel() }}</span></td>
                        <td class="text-end money">{{ pkr($sale->sale_price, false) }}</td>
                        <td class="text-end money">{{ pkr($sale->receivedTotal(), false) }}</td>
                        <td class="text-end money">{{ pkr($sale->outstandingReceivable(), false) }}</td>
                        <td class="text-end {{ money_class($sale->realized_profit) }}">
                            {{ $sale->isFinalized() ? pkr($sale->realized_profit, false) : '-' }}
                        </td>
                        <td class="text-end"><a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state">No sales recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sales->links() }}</div>
@endsection
