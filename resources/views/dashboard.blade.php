@extends('layouts.app')
@section('title', 'Dashboard')
@section('subheading', 'A snapshot of the business. Accounting profit and cash in hand are shown as separate figures.')

@section('content')
    <div class="row g-3 mb-3">
        @php
            $tiles = [
                ['label' => 'Cash and bank', 'value' => $summary['cash'], 'icon' => 'bank', 'hint' => 'Money actually available'],
                ['label' => 'Realised profit on sales', 'value' => $realizedProfit, 'icon' => 'graph-up-arrow', 'hint' => 'Accounting result, not cash'],
                ['label' => 'Buyer receivables', 'value' => $summary['buyer_receivable'], 'icon' => 'arrow-down-left-circle', 'hint' => 'Owed to you, not yet received'],
                ['label' => 'Seller payables', 'value' => $summary['seller_payable'], 'icon' => 'arrow-up-right-circle', 'hint' => 'You still owe this'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div class="col-6 col-xl-3">
                <div class="card kpi-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="kpi-label">{{ $tile['label'] }}</div>
                            <i class="bi bi-{{ $tile['icon'] }} text-secondary"></i>
                        </div>
                        <div class="kpi-value {{ money_class($tile['value']) }}">{{ pkr($tile['value']) }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $tile['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Property inventory</div>
                    <div class="d-flex justify-content-between"><span>Available</span><strong>{{ $counts['available'] ?? 0 }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Reserved</span><strong>{{ $counts['reserved'] ?? 0 }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Under sale</span><strong>{{ $counts['under_sale'] ?? 0 }}</strong></div>
                    <div class="d-flex justify-content-between text-muted"><span>Sold</span><strong>{{ $counts['sold'] ?? 0 }}</strong></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Active against your plan</span>
                        <strong>{{ $activeProperties }}{{ (int) $propertyLimit === -1 ? '' : ' / '.$propertyLimit }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Still to settle</div>
                    <div class="d-flex justify-content-between"><span>Investor profit payable</span><strong class="{{ money_class($investorPayable) }}">{{ pkr($investorPayable, false) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Expense payables</span><strong class="money">{{ pkr($summary['expense_payable'], false) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Buyer advances held</span><strong class="money">{{ pkr($summary['buyer_advance'], false) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Overdue installments</span><strong class="money">{{ pkr($overdueTotal, false) }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Capital employed</div>
                    <div class="d-flex justify-content-between"><span>Unsold property cost</span><strong class="money">{{ pkr($summary['property_cost'], false) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Investor capital</span><strong class="money">{{ pkr($summary['investor_capital'], false) }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Owner capital</span><strong class="money">{{ pkr($summary['owner_capital'], false) }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="kpi-label mb-2">Waiting for approval</div>
                    @forelse ($pendingApprovals as $request)
                        <div class="small mb-2">
                            <a href="{{ route('approvals.show', $request) }}">{{ $request->actionLabel() }}</a>
                            <div class="text-muted">{{ pkr($request->amount) }} &middot; {{ $request->requester?->name }}</div>
                        </div>
                    @empty
                        <div class="text-muted small">Nothing is waiting.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Overdue buyer installments</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Property</th><th>Buyer</th><th>Due</th><th class="text-end">Remaining</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($overdueInstallments as $installment)
                            <tr>
                                <td><a href="{{ route('sales.show', $installment->sale_id) }}">{{ $installment->sale->property->reference }}</a></td>
                                <td>{{ $installment->sale->buyer->name }}</td>
                                <td class="text-danger">{{ pms_date($installment->due_date) }}</td>
                                <td class="text-end money">{{ pkr($installment->remaining(), false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Nothing is overdue.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Falling due in the next 30 days</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Property</th><th>Buyer</th><th>Due</th><th class="text-end">Remaining</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($upcomingInstallments as $installment)
                            <tr>
                                <td><a href="{{ route('sales.show', $installment->sale_id) }}">{{ $installment->sale->property->reference }}</a></td>
                                <td>{{ $installment->sale->buyer->name }}</td>
                                <td>{{ pms_date($installment->due_date) }}</td>
                                <td class="text-end money">{{ pkr($installment->remaining(), false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Nothing due in the next 30 days.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
