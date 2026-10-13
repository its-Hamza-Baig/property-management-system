@extends('layouts.app')
@section('title', 'Investor statement')
@section('heading', 'Statement for '.$investor->name)
@section('subheading', 'Prepared '.now()->format('d M Y'))

@section('actions')
    <div class="btn-group">
        <a href="{{ route('reports.show', 'investor-distributions') }}?investor_id={{ $investor->id }}" class="btn btn-sm btn-outline-secondary">Open as report</a>
        <a href="{{ route('investors.show', $investor) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Capital invested</div><div class="kpi-value money">{{ pkr($totalCapital, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Profit calculated</div><div class="kpi-value {{ money_class($profitEarned) }}">{{ pkr($profitEarned, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Profit paid</div><div class="kpi-value money">{{ pkr($profitPaid, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Outstanding</div><div class="kpi-value {{ money_class($profitOutstanding) }}">{{ pkr($profitOutstanding, false) }}</div>
        </div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Capital placed</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Property</th><th>From</th><th>To</th><th class="text-end">Capital</th><th class="text-end">Own %</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($investor->allocations->sortByDesc('id') as $allocation)
                    <tr>
                        <td>{{ $allocation->property->reference }}</td>
                        <td>{{ pms_date($allocation->effective_from) }}</td>
                        <td>{{ $allocation->effective_to ? pms_date($allocation->effective_to) : 'current' }}</td>
                        <td class="text-end money">{{ pkr($allocation->capital_amount, false) }}</td>
                        <td class="text-end">{{ rtrim(rtrim($allocation->ownership_percentage, '0'), '.') }}</td>
                        <td>{{ ucfirst($allocation->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No capital recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Profit and distributions</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Property</th><th class="text-end">Share %</th><th class="text-end">Calculated</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($payables as $payable)
                    <tr>
                        <td>{{ $payable->sale->property->reference }}</td>
                        <td class="text-end">{{ rtrim(rtrim($payable->profit_share_percentage, '0'), '.') }}</td>
                        <td class="text-end {{ money_class($payable->profit_amount) }}">{{ pkr($payable->profit_amount, false) }}</td>
                        <td class="text-end money">{{ pkr($payable->paid_amount, false) }}</td>
                        <td class="text-end {{ money_class($payable->outstanding()) }}">{{ pkr($payable->outstanding(), false) }}</td>
                        <td>{{ $payable->statusLabel() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No profit has been calculated for this investor yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold">Payments received by the investor</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Account</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse ($investor->distributions->where('status', 'confirmed')->sortByDesc('payment_date') as $distribution)
                    <tr>
                        <td>{{ pms_date($distribution->payment_date) }}</td>
                        <td>{{ $distribution->financialAccount->displayName() }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $distribution->method)) }}</td>
                        <td>{{ $distribution->reference ?: '-' }}</td>
                        <td class="text-end money">{{ pkr($distribution->amount, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No distributions paid yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
