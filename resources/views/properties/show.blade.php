@extends('layouts.app')
@section('title', $property->reference)
@section('heading', $property->reference.' — '.$property->title)
@section('subheading')
    {{ $property->typeLabel() }}
    @if ($property->project_name) &middot; {{ $property->project_name }} @endif
    @if ($property->city) &middot; {{ $property->city }} @endif
@endsection

@section('actions')
    <div class="btn-group">
        <a href="{{ route('properties.ledger', $property) }}" class="btn btn-outline-secondary btn-sm">Ledger</a>
        <a href="{{ route('properties.allocations', $property) }}" class="btn btn-outline-secondary btn-sm">Investors</a>
        @can('update', $property)
            <a href="{{ route('properties.edit', $property) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card kpi-card h-100"><div class="card-body">
                <div class="kpi-label">Status</div>
                <div><span class="badge {{ $property->statusBadgeClass() }} fs-6">{{ $property->statusLabel() }}</span></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100"><div class="card-body">
                <div class="kpi-label">Purchase price</div>
                <div class="kpi-value money">{{ $property->purchase ? pkr($property->purchase->purchase_price, false) : '-' }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100"><div class="card-body">
                <div class="kpi-label">Capitalised expenses</div>
                <div class="kpi-value money">{{ pkr($capitalised, false) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100"><div class="card-body">
                <div class="kpi-label">Total cost base</div>
                <div class="kpi-value money">{{ pkr($totalCost, false) }}</div>
                <div class="text-muted" style="font-size:.75rem">Caps investor capital</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Purchase</span>
                    @if (! $property->purchase)
                        @can('create', App\Models\PropertyPurchase::class)
                            <a href="{{ route('purchases.create', ['property_id' => $property->id]) }}" class="btn btn-sm btn-outline-primary">Record purchase</a>
                        @endcan
                    @endif
                </div>
                <div class="card-body">
                    @if ($property->purchase)
                        @php $purchase = $property->purchase; @endphp
                        <dl class="row mb-0 small">
                            <dt class="col-5">Purchase date</dt><dd class="col-7">{{ pms_date($purchase->purchase_date) }}</dd>
                            <dt class="col-5">Price</dt><dd class="col-7 money">{{ pkr($purchase->purchase_price) }}</dd>
                            <dt class="col-5">Status</dt><dd class="col-7">{{ $purchase->statusLabel() }}</dd>
                            <dt class="col-5">Paid to sellers</dt><dd class="col-7 money">{{ pkr($purchase->paidTotal()) }}</dd>
                            <dt class="col-5">Still owed</dt><dd class="col-7 money">{{ pkr($purchase->outstandingPayable()) }}</dd>
                        </dl>
                        <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-outline-secondary mt-2">Open purchase</a>
                    @else
                        <div class="text-muted small">No purchase recorded yet. The cost base stays at zero until one is confirmed.</div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Expenses</span>
                    @can('create', App\Models\Expense::class)
                        <a href="{{ route('expenses.create', ['property_id' => $property->id]) }}" class="btn btn-sm btn-outline-primary">Add expense</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Treatment</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        @forelse ($property->expenses as $expense)
                            <tr>
                                <td>{{ pms_date($expense->expense_date) }}</td>
                                <td><a href="{{ route('expenses.show', $expense) }}">{{ $expense->category->name }}</a></td>
                                <td><span class="badge {{ $expense->is_capitalized ? 'bg-info text-dark' : 'bg-light text-dark' }}">{{ $expense->is_capitalized ? 'Added to cost' : 'Period expense' }}</span></td>
                                <td>{{ $expense->statusLabel() }}</td>
                                <td class="text-end money">{{ pkr($expense->amount, false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No expenses recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Sales history</span>
                    @can('create', App\Models\Sale::class)
                        @unless ($property->isSold())
                            <a href="{{ route('sales.create', ['property_id' => $property->id]) }}" class="btn btn-sm btn-outline-primary">Create sale</a>
                        @endunless
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Buyer</th><th>Status</th><th class="text-end">Price</th><th class="text-end">Profit</th></tr></thead>
                        <tbody>
                        @forelse ($property->sales->sortByDesc('id') as $sale)
                            <tr>
                                <td>{{ pms_date($sale->agreement_date) }}</td>
                                <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->buyer->name }}</a></td>
                                <td><span class="badge {{ $sale->statusBadgeClass() }}">{{ $sale->statusLabel() }}</span></td>
                                <td class="text-end money">{{ pkr($sale->sale_price, false) }}</td>
                                <td class="text-end {{ money_class($sale->realized_profit) }}">
                                    {{ $sale->isFinalized() ? pkr($sale->realized_profit, false) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No sales recorded. Cancelled sales also appear here permanently.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Investor allocations</span>
                    <a href="{{ route('properties.allocations', $property) }}" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Participant</th><th class="text-end">Capital</th><th class="text-end">Own %</th><th class="text-end">Profit %</th></tr></thead>
                        <tbody>
                        @forelse ($property->allocations->where('status', 'active')->whereNull('effective_to') as $allocation)
                            <tr>
                                <td>{{ $allocation->participantName() }}</td>
                                <td class="text-end money">{{ pkr($allocation->capital_amount, false) }}</td>
                                <td class="text-end">{{ rtrim(rtrim($allocation->ownership_percentage, '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim($allocation->profit_share_percentage, '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No investors allocated. The whole property belongs to the account holder.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Reservations</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Buyer</th><th>Status</th><th class="text-end">Token</th></tr></thead>
                        <tbody>
                        @forelse ($property->reservations->sortByDesc('id') as $reservation)
                            <tr>
                                <td>{{ pms_date($reservation->reservation_date) }}</td>
                                <td><a href="{{ route('reservations.show', $reservation) }}">{{ $reservation->buyer->name }}</a></td>
                                <td>{{ $reservation->statusLabel() }}</td>
                                <td class="text-end money">{{ pkr($reservation->token_amount, false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No reservations.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.documents', [
                'documents' => $property->documents,
                'ownerType' => 'property',
                'ownerId' => $property->id,
                'defaultCategory' => 'other',
            ])
        </div>
    </div>
@endsection
