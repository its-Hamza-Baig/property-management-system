@extends('layouts.app')
@section('title', 'Purchase')
@section('heading', 'Purchase of '.$purchase->property->reference)
@section('subheading', $purchase->property->title)

@section('actions')
    <div class="btn-group">
        <a href="{{ route('properties.show', $purchase->property) }}" class="btn btn-sm btn-outline-secondary">Property</a>
        @can('update', $purchase)
            <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-sm btn-outline-secondary">Edit draft</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Purchase price</div><div class="kpi-value money">{{ pkr($purchase->purchase_price, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Paid to sellers</div><div class="kpi-value money">{{ pkr($paid, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Still owed</div><div class="kpi-value money">{{ pkr($outstanding, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Status</div>
            <div><span class="badge fs-6 {{ $purchase->isConfirmed() ? 'bg-success' : 'bg-secondary' }}">{{ $purchase->statusLabel() }}</span></div>
        </div></div></div>
    </div>

    @unless ($purchase->isConfirmed())
        @can('confirm', $purchase)
            <div class="card mb-3 border-primary">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-semibold">This purchase is still a draft</div>
                        <div class="text-muted small">
                            Confirming records {{ pkr($purchase->purchase_price) }} as property cost and creates the payable to the sellers.
                            @if ($needsApproval) <strong>Your settings require approval for purchases of this size.</strong> @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('purchases.confirm', $purchase) }}">
                        @csrf
                        <button class="btn btn-primary">Confirm purchase</button>
                    </form>
                </div>
            </div>
        @endcan
    @endunless

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Payments to sellers</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Seller</th><th>Account</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($purchase->payments->sortByDesc('id') as $payment)
                            <tr class="{{ $payment->status === 'reversed' ? 'text-muted' : '' }}">
                                <td>{{ pms_date($payment->payment_date) }}</td>
                                <td>{{ $payment->seller?->name ?: 'Not itemised' }}</td>
                                <td>{{ $payment->financialAccount->name }}</td>
                                <td>{{ $payment->methodLabel() }}</td>
                                <td class="text-end money">{{ pkr($payment->amount, false) }}</td>
                                <td class="text-end">
                                    @if ($payment->isConfirmed())
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#reverse-{{ $payment->id }}">Reverse</button>
                                    @else
                                        <span class="badge bg-secondary">Reversed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($purchase->payments->where('status', 'confirmed') as $payment)
                <div class="modal fade" id="reverse-{{ $payment->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" action="{{ route('seller-payments.reverse', $payment) }}" class="modal-content">
                            @csrf
                            <div class="modal-header"><h5 class="modal-title">Reverse payment of {{ pkr($payment->amount) }}</h5></div>
                            <div class="modal-body">
                                <p class="small text-muted">
                                    The original entry is kept and a linked reversing entry is added. Nothing is deleted.
                                </p>
                                <label class="form-label required">Reason</label>
                                <textarea name="reason" rows="3" class="form-control" required minlength="5"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-danger">Reverse</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach

            @include('partials.documents', [
                'documents' => $purchase->documents,
                'ownerType' => 'purchase',
                'ownerId' => $purchase->id,
                'defaultCategory' => 'purchase_agreement',
            ])
        </div>

        <div class="col-lg-5">
            @if ($purchase->sellerShares->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Seller-wise balance</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Seller</th><th class="text-end">Agreed</th><th class="text-end">Outstanding</th></tr></thead>
                            <tbody>
                            @foreach ($purchase->sellerShares as $share)
                                <tr>
                                    <td>{{ $share->seller->name }}</td>
                                    <td class="text-end money">{{ pkr($share->amount, false) }}</td>
                                    <td class="text-end money">{{ pkr($purchase->outstandingForSeller($share->seller_id), false) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @can('recordPayment', $purchase)
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Record a payment</div>
                    <div class="card-body">
                        @if ($accounts->isEmpty())
                            <div class="text-muted small">Add a cash or bank account first.</div>
                        @else
                            <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Amount</label>
                                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $outstanding->toDecimal() }}"
                                           class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Date</label>
                                    <input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1 required">Paid from</label>
                                    <select name="financial_account_id" class="form-select form-select-sm" required>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->displayName() }} — {{ pkr($account->currentBalance()) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Seller</label>
                                    <select name="seller_id" class="form-select form-select-sm">
                                        <option value="">Not itemised</option>
                                        @foreach ($purchase->sellerShares as $share)
                                            <option value="{{ $share->seller_id }}">{{ $share->seller->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Method</label>
                                    <select name="method" class="form-select form-select-sm" required>
                                        @foreach (App\Models\SellerPayment::METHODS as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Reference</label>
                                    <input name="reference" class="form-control form-control-sm">
                                </div>
                                <div class="col-12"><button class="btn btn-sm btn-primary">Record payment</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
