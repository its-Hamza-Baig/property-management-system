@extends('layouts.app')
@section('title', 'Sale')
@section('heading', 'Sale of '.$sale->property->reference)
@section('subheading', 'Buyer: '.$sale->buyer->name.' · Agreement '.pms_date($sale->agreement_date))

@section('actions')
    <div class="btn-group">
        <a href="{{ route('properties.show', $sale->property) }}" class="btn btn-sm btn-outline-secondary">Property</a>
        @can('update', $sale)
            <a href="{{ route('sales.edit', $sale) }}" class="btn btn-sm btn-outline-secondary">Edit draft</a>
        @endcan
        @can('correct', $sale)
            <a href="{{ route('sales.correct', $sale) }}" class="btn btn-sm btn-outline-warning">Correct</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Status</div>
            <div><span class="badge {{ $sale->statusBadgeClass() }}">{{ $sale->statusLabel() }}</span></div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Sale value</div><div class="kpi-value money">{{ pkr($sale->sale_price, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Cash received</div><div class="kpi-value money">{{ pkr($received, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Still receivable</div><div class="kpi-value money">{{ pkr($outstanding, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">{{ $sale->isFinalized() ? 'Cost applied' : 'Cost base now' }}</div>
            <div class="kpi-value money">{{ pkr($projectedCost, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-2"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Realised profit</div>
            <div class="kpi-value {{ money_class($sale->realized_profit) }}">
                {{ $sale->isFinalized() ? pkr($sale->realized_profit, false) : '—' }}
            </div>
        </div></div></div>
    </div>

    @if (! $sale->isFinalized() && ! $sale->isCancelled())
        @can('finalize', $sale)
            <div class="card mb-3 border-primary"><div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-semibold">Finalise this sale</div>
                        <div class="text-muted small">
                            Profit will be {{ pkr(\App\Support\Money::of($sale->sale_price)->minus($projectedCost)->minus($saleExpenses)) }}
                            = sale price {{ pkr($sale->sale_price) }} − cost {{ pkr($projectedCost) }} − sale expenses {{ pkr($saleExpenses) }}.
                            A sale can be finalised even when the buyer still owes money.
                        </div>
                    </div>
                    <form method="POST" action="{{ route('sales.finalize', $sale) }}"
                          onsubmit="return confirm('Finalise this sale? The cost applied will be frozen onto it.')">
                        @csrf
                        <button class="btn btn-primary">Finalise sale</button>
                    </form>
                </div>
            </div></div>
        @endcan
    @endif

    @if ($sale->isCancelled())
        <div class="alert alert-secondary">
            <strong>Cancelled on {{ pms_date($sale->cancellation_date) }}.</strong> {{ $sale->cancellation_reason }}
            <div class="small mt-1">
                Forfeited {{ pkr($sale->forfeited_amount) }} · Refund still due {{ pkr($refundDue) }}.
                This record is kept permanently; a later sale of the property is a separate record.
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Installment plan</div>
                <div class="card-body">
                    @can('update', $sale)
                        <form method="POST" action="{{ route('sales.schedule', $sale) }}">
                            @csrf @method('PUT')
                            <table class="table table-sm align-middle">
                                <thead class="table-light"><tr><th>#</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Status</th></tr></thead>
                                <tbody>
                                @php $rows = $sale->installments->values(); @endphp
                                @for ($i = 0; $i < max($rows->count(), 1) + 2; $i++)
                                    @php $row = $rows[$i] ?? null; @endphp
                                    <tr>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td><input type="date" name="rows[{{ $i }}][due_date]"
                                                   value="{{ optional($row?->due_date)->toDateString() }}" class="form-control form-control-sm"></td>
                                        <td><input type="number" step="0.01" min="0" name="rows[{{ $i }}][amount]"
                                                   value="{{ $row?->amount }}" class="form-control form-control-sm"></td>
                                        <td class="money small">{{ $row ? pkr($row->paid_amount, false) : '' }}</td>
                                        <td>@if ($row)<span class="badge {{ $row->statusBadgeClass() }}">{{ $row->statusLabel() }}</span>@endif</td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                            <button class="btn btn-sm btn-outline-primary">Save plan</button>
                            <span class="text-muted small ms-2">A schedule is a plan. It moves no money by itself.</span>
                        </form>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>#</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Remaining</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse ($sale->installments as $row)
                                <tr>
                                    <td>{{ $row->installment_no }}</td><td>{{ pms_date($row->due_date) }}</td>
                                    <td class="text-end money">{{ pkr($row->amount, false) }}</td>
                                    <td class="text-end money">{{ pkr($row->remaining(), false) }}</td>
                                    <td><span class="badge {{ $row->statusBadgeClass() }}">{{ $row->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">No schedule set.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    @endcan
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Receipts and refunds</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Account</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($sale->payments->sortByDesc('id') as $payment)
                            <tr class="{{ $payment->status === 'reversed' ? 'text-muted' : '' }}">
                                <td>{{ pms_date($payment->payment_date) }}</td>
                                <td>{{ $payment->typeLabel() }}</td>
                                <td>{{ $payment->financialAccount->name }}</td>
                                <td class="text-end money">{{ pkr($payment->amount, false) }}</td>
                                <td class="text-end">
                                    @if ($payment->isConfirmed())
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bp-{{ $payment->id }}">Reverse</button>
                                    @else
                                        <span class="badge bg-secondary">Reversed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No receipts yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach ($sale->payments->where('status', 'confirmed') as $payment)
                <div class="modal fade" id="bp-{{ $payment->id }}" tabindex="-1">
                    <div class="modal-dialog"><form method="POST" action="{{ route('buyer-payments.reverse', $payment) }}" class="modal-content">
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

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Sale expenses</span>
                    @can('create', App\Models\Expense::class)
                        <a href="{{ route('expenses.create', ['sale_id' => $sale->id, 'property_id' => $sale->property_id]) }}"
                           class="btn btn-sm btn-outline-primary">Add</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        @forelse ($sale->expenses as $expense)
                            <tr>
                                <td>{{ pms_date($expense->expense_date) }}</td>
                                <td><a href="{{ route('expenses.show', $expense) }}">{{ $expense->category->name }}</a></td>
                                <td>{{ $expense->statusLabel() }}</td>
                                <td class="text-end money">{{ pkr($expense->amount, false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No sale expenses, such as commission or transfer charges.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($sale->isFinalized())
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Investor profit</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Participant</th><th class="text-end">Share %</th>
                                <th class="text-end">Calculated</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($sale->profitPayables as $payable)
                                <tr>
                                    <td>{{ $payable->participantName() }}</td>
                                    <td class="text-end">{{ rtrim(rtrim($payable->profit_share_percentage, '0'), '.') }}</td>
                                    <td class="text-end {{ money_class($payable->profit_amount) }}">{{ pkr($payable->profit_amount, false) }}</td>
                                    <td class="text-end money">{{ pkr($payable->paid_amount, false) }}</td>
                                    <td class="text-end {{ money_class($payable->outstanding()) }}">{{ pkr($payable->outstanding(), false) }}</td>
                                    <td class="text-end">
                                        @if (! $payable->is_owner && $payable->outstanding()->isPositive())
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pay-{{ $payable->id }}">Pay</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        The account holder share stays in owner capital. Paying an investor settles this payable and
                        is never recorded as a property expense.
                    </div>
                </div>

                @foreach ($sale->profitPayables->where('is_owner', false) as $payable)
                    <div class="modal fade" id="pay-{{ $payable->id }}" tabindex="-1">
                        <div class="modal-dialog"><form method="POST" action="{{ route('profit-payables.pay', $payable) }}" class="modal-content">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <div class="modal-header"><h5 class="modal-title">Pay {{ $payable->participantName() }}</h5></div>
                            <div class="modal-body row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Amount</label>
                                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $payable->outstanding()->toDecimal() }}"
                                           value="{{ $payable->outstanding()->toDecimal() }}" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Date</label>
                                    <input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1 required">From account</label>
                                    <select name="financial_account_id" class="form-select" required>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->displayName() }} — {{ pkr($account->currentBalance()) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Method</label>
                                    <select name="method" class="form-select" required>
                                        @foreach (App\Models\SellerPayment::METHODS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Reference</label>
                                    <input name="reference" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary">Pay</button>
                            </div>
                        </form></div>
                    </div>
                @endforeach
            @endif

            @include('partials.documents', [
                'documents' => $sale->documents,
                'ownerType' => 'sale',
                'ownerId' => $sale->id,
                'defaultCategory' => 'sale_agreement',
            ])
        </div>

        <div class="col-lg-5">
            @can('recordPayment', $sale)
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Record a receipt</div>
                    <div class="card-body">
                        @if ($accounts->isEmpty())
                            <div class="text-muted small">Add a cash or bank account first.</div>
                        @else
                            <form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Amount</label>
                                    <input name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Date</label>
                                    <input name="payment_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1 required">Type</label>
                                    <select name="type" class="form-select form-select-sm" required>
                                        @foreach (App\Models\BuyerPayment::TYPES as $v => $l)
                                            <option value="{{ $v }}" @selected($v === 'installment')>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1">Against installment</label>
                                    <select name="installment_id" class="form-select form-select-sm">
                                        <option value="">Not linked</option>
                                        @foreach ($sale->installments as $row)
                                            <option value="{{ $row->id }}">#{{ $row->installment_no }} due {{ pms_date($row->due_date) }} — {{ pkr($row->remaining()) }} left</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1 required">Account</label>
                                    <select name="financial_account_id" class="form-select form-select-sm" required>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Method</label>
                                    <select name="method" class="form-select form-select-sm" required>
                                        @foreach (App\Models\SellerPayment::METHODS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Reference</label>
                                    <input name="reference" class="form-control form-control-sm">
                                </div>
                                <div class="col-12"><button class="btn btn-sm btn-primary">Record receipt</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Money summary</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7">Sale value</dt><dd class="col-5 text-end money">{{ pkr($sale->sale_price, false) }}</dd>
                        <dt class="col-7">Cash received</dt><dd class="col-5 text-end money">{{ pkr($received, false) }}</dd>
                        <dt class="col-7">Still receivable</dt><dd class="col-5 text-end money">{{ pkr($outstanding, false) }}</dd>
                        <dt class="col-7">Advance held</dt><dd class="col-5 text-end money">{{ pkr($advanceHeld, false) }}</dd>
                        <dt class="col-7">Sale expenses</dt><dd class="col-5 text-end money">{{ pkr($saleExpenses, false) }}</dd>
                    </dl>
                    <hr>
                    <div class="small text-muted">
                        Cash received and realised profit are different numbers and are never substituted for each other.
                    </div>
                </div>
            </div>

            @can('cancel', $sale)
                @unless ($sale->isCancelled())
                    <div class="card">
                        <div class="card-header bg-white fw-semibold">Cancel this sale</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('sales.cancel', $sale) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label small mb-1 required">Reason</label>
                                    <textarea name="cancellation_reason" rows="2" class="form-control form-control-sm" required minlength="5"></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1 required">Date</label>
                                        <input name="cancellation_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Amount forfeited</label>
                                        <input name="forfeited_amount" type="number" step="0.01" min="0" max="{{ $advanceHeld->toDecimal() }}"
                                               value="0" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="form-check mt-2">
                                    <input type="hidden" name="return_to_available" value="0">
                                    <input class="form-check-input" type="checkbox" name="return_to_available" value="1" id="rta" checked>
                                    <label class="form-check-label small" for="rta">Put the property back to available</label>
                                </div>
                                <button class="btn btn-sm btn-outline-danger mt-2"
                                        onclick="return confirm('Cancel this sale? The record is kept permanently.')">Cancel sale</button>
                            </form>
                        </div>
                    </div>
                @endunless
            @endcan
        </div>
    </div>
@endsection
