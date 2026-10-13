@extends('layouts.app')
@section('title', 'My overview')
@section('heading', 'Welcome, '.$investor->name)
@section('subheading', 'You see only your own investments and your own statement.')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Active investments</div><div class="kpi-value">{{ $activeCount }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Capital invested</div><div class="kpi-value money">{{ pkr($totalCapital, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Profit calculated</div><div class="kpi-value {{ money_class($profitEarned) }}">{{ pkr($profitEarned, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Still owed to you</div><div class="kpi-value {{ money_class($profitOutstanding) }}">{{ pkr($profitOutstanding, false) }}</div>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold">Your current holdings</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Property</th><th>Status</th><th class="text-end">Capital</th><th class="text-end">Ownership %</th><th class="text-end">Profit share %</th></tr></thead>
                <tbody>
                @forelse ($allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->property->reference }} — {{ $allocation->property->title }}</td>
                        <td><span class="badge {{ $allocation->property->statusBadgeClass() }}">{{ $allocation->property->statusLabel() }}</span></td>
                        <td class="text-end money">{{ pkr($allocation->capital_amount, false) }}</td>
                        <td class="text-end">{{ rtrim(rtrim($allocation->ownership_percentage, '0'), '.') }}</td>
                        <td class="text-end">{{ rtrim(rtrim($allocation->profit_share_percentage, '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">You have no active investments at the moment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
