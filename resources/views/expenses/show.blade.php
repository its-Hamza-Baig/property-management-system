@extends('layouts.app')
@section('title', 'Expense')
@section('heading', $expense->category->name)
@section('subheading', pms_date($expense->expense_date).' · '.($expense->description ?: 'No description'))

@section('actions')
    @can('update', $expense)
        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-secondary">Edit draft</a>
    @endcan
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Amount</div><div class="kpi-value money">{{ pkr($expense->amount, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Paid</div><div class="kpi-value money">{{ pkr($paid, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Outstanding</div><div class="kpi-value money">{{ pkr($outstanding, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Treatment</div>
            <div><span class="badge fs-6 {{ $expense->is_capitalized ? 'bg-info text-dark' : 'bg-light text-dark' }}">
                {{ $expense->is_capitalized ? 'Added to property cost' : 'Period expense' }}</span></div>
        </div></div></div>
    </div>

    @unless ($expense->isConfirmed())
        @can('confirm', $expense)
            <div class="card mb-3 border-primary"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">This expense is a draft ({{ $expense->statusLabel() }})</div>
                    <div class="text-muted small">Confirming records the cost and creates a payable of {{ pkr($expense->amount) }}.</div>
                </div>
                <form method="POST" action="{{ route('expenses.confirm', $expense) }}">
                    @csrf
                    <button class="btn btn-primary">Confirm expense</button>
                </form>
            </div></div>
        @endcan
    @endunless

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Payments</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Account</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($expense->payments->sortByDesc('id') as $payment)
                            <tr class="{{ $payment->status === 'reversed' ? 'text-muted' : '' }}">
                                <td>{{ pms_date($payment->payment_date) }}</td>
                                <td>{{ $payment->financialAccount->name }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                                <td class="text-end money">{{ pkr($payment->amount, false) }}</td>
                                <td class="text-end">
                                    @if ($payment->isConfirmed())
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rev-{{ $payment->id }}">Reverse</button>
                                    @else
                                        <span class="badge bg-secondary">Reversed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No payments made yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($expense->payments->where('status', 'confirmed') as $payment)
                <div class="modal fade" id="rev-{{ $payment->id }}" tabindex="-1">
                    <div class="modal-dialog"><form method="POST" action="{{ route('expense-payments.reverse', $payment) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Reverse {{ pkr($payment->amount) }}</h5></div>
                        <div class="modal-body">
                            <label class="form-label required">Reason</label>
                            <textarea name="reason" rows="3" class="form-control" required minlength="5"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-danger">Reverse</button>
                        </div>
                    </form></div>
                </div>
            @endforeach

            @if ($expense->fundingSources->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Funded by</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Party</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                            @foreach ($expense->fundingSources as $source)
                                <tr><td>{{ $source->funderLabel() }}</td><td class="text-end money">{{ pkr($source->amount, false) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        Whose capital this represents. Who physically paid it is recorded in the payments above.
                    </div>
                </div>
            @endif

            @include('partials.documents', [
                'documents' => $expense->documents,
                'ownerType' => 'expense',
                'ownerId' => $expense->id,
                'defaultCategory' => 'expense_bill',
            ])
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Status</dt><dd class="col-7">{{ $expense->statusLabel() }}</dd>
                        <dt class="col-5">Payment status</dt><dd class="col-7">{{ $expense->paymentStatusLabel() }}</dd>
                        <dt class="col-5">Property</dt><dd class="col-7">{{ $expense->property?->reference ?: '-' }}</dd>
                        <dt class="col-5">Linked sale</dt><dd class="col-7">{{ $expense->sale ? $expense->sale->property->reference : '-' }}</dd>
                        <dt class="col-5">Confirmed</dt><dd class="col-7">{{ pms_date($expense->confirmed_at) }}</dd>
                    </dl>
                </div>
            </div>

            @if ($expense->isConfirmed() && $outstanding->isPositive())
                @can('pay', $expense)
                    <div class="card mb-3">
                        <div class="card-header bg-white fw-semibold">Record a payment</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('expenses.payments.store', $expense) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Amount</label>
                                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $outstanding->toDecimal() }}"
                                           value="{{ $outstanding->toDecimal() }}" class="form-control form-control-sm" required>
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
                                    <label class="form-label small mb-1 required">Method</label>
                                    <select name="method" class="form-select form-select-sm" required>
                                        @foreach (App\Models\SellerPayment::METHODS as $v => $l)
                                            <option value="{{ $v }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Reference</label>
                                    <input name="reference" class="form-control form-control-sm">
                                </div>
                                <div class="col-12"><button class="btn btn-sm btn-primary">Record payment</button></div>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            @can('cancel', $expense)
                <div class="card">
                    <div class="card-header bg-white fw-semibold">Cancel this expense</div>
                    <div class="card-body">
                        <p class="text-muted small">
                            A confirmed expense is cancelled with a reversing entry, never deleted.
                            Reverse any payments against it first.
                        </p>
                        <form method="POST" action="{{ route('expenses.cancel', $expense) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small mb-1 required">Reason</label>
                                <textarea name="reason" rows="2" class="form-control form-control-sm" required minlength="5"></textarea>
                            </div>
                            <button class="btn btn-sm btn-outline-danger">Cancel expense</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
