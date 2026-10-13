@extends('layouts.app')
@section('title', 'Purchases')
@section('subheading', 'The purchase price becomes cost once, when the purchase is confirmed. Later payments only settle the payable.')

@section('actions')
    @can('create', App\Models\PropertyPurchase::class)
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Record purchase</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search property</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'] as $v => $l)
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
                    <tr><th>Date</th><th>Property</th><th>Seller(s)</th><th>Status</th><th class="text-end">Price</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>{{ pms_date($purchase->purchase_date) }}</td>
                        <td><a href="{{ route('purchases.show', $purchase) }}" class="fw-semibold">{{ $purchase->property->reference }}</a></td>
                        <td class="small">{{ $purchase->sellerShares->map(fn ($s) => $s->seller->name)->join(', ') ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $purchase->isConfirmed() ? 'bg-success' : ($purchase->status === 'pending_approval' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                {{ $purchase->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-end money">{{ pkr($purchase->purchase_price, false) }}</td>
                        <td class="text-end money">{{ pkr($purchase->paidTotal(), false) }}</td>
                        <td class="text-end money">{{ pkr($purchase->outstandingPayable(), false) }}</td>
                        <td class="text-end"><a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No purchases recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $purchases->links() }}</div>
@endsection
