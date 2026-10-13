@extends('layouts.app')
@section('title', $investor->name)
@section('subheading', 'Capital, profit calculated, profit paid and profit outstanding are four different figures.')

@section('actions')
    <div class="btn-group">
        <a href="{{ route('investors.statement', $investor) }}" class="btn btn-sm btn-outline-secondary">Statement</a>
        @can('update', $investor)
            <a href="{{ route('investors.edit', $investor) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Capital invested</div>
            <div class="kpi-value money">{{ pkr($totalCapital, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Profit calculated</div>
            <div class="kpi-value {{ money_class($profitEarned) }}">{{ pkr($profitEarned, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Profit paid</div>
            <div class="kpi-value money">{{ pkr($profitPaid, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card h-100"><div class="card-body">
            <div class="kpi-label">Still owed</div>
            <div class="kpi-value {{ money_class($profitOutstanding) }}">{{ pkr($profitOutstanding, false) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Investments</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Property</th><th>Status</th><th class="text-end">Capital</th><th class="text-end">Own %</th><th class="text-end">Profit %</th></tr></thead>
                        <tbody>
                        @forelse ($investor->allocations->where('status', 'active') as $allocation)
                            <tr>
                                <td><a href="{{ route('properties.show', $allocation->property) }}">{{ $allocation->property->reference }}</a></td>
                                <td><span class="badge {{ $allocation->property->statusBadgeClass() }}">{{ $allocation->property->statusLabel() }}</span></td>
                                <td class="text-end money">{{ pkr($allocation->capital_amount, false) }}</td>
                                <td class="text-end">{{ rtrim(rtrim($allocation->ownership_percentage, '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim($allocation->profit_share_percentage, '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No active investments.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Profit by sale</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Property</th><th class="text-end">Share %</th><th class="text-end">Calculated</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th></tr></thead>
                        <tbody>
                        @forelse ($investor->profitPayables as $payable)
                            <tr>
                                <td><a href="{{ route('sales.show', $payable->sale) }}">{{ $payable->sale->property->reference }}</a></td>
                                <td class="text-end">{{ rtrim(rtrim($payable->profit_share_percentage, '0'), '.') }}</td>
                                <td class="text-end {{ money_class($payable->profit_amount) }}">{{ pkr($payable->profit_amount, false) }}</td>
                                <td class="text-end money">{{ pkr($payable->paid_amount, false) }}</td>
                                <td class="text-end {{ money_class($payable->outstanding()) }}">{{ pkr($payable->outstanding(), false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No finalised sales yet for this investor.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">Phone</dt><dd class="col-8">{{ $investor->phone ?: '-' }}</dd>
                        <dt class="col-4">Email</dt><dd class="col-8">{{ $investor->email ?: '-' }}</dd>
                        <dt class="col-4">CNIC</dt><dd class="col-8">{{ $investor->cnic ?: '-' }}</dd>
                        <dt class="col-4">Address</dt><dd class="col-8">{{ $investor->address ?: '-' }}</dd>
                        <dt class="col-4">Added</dt><dd class="col-8">{{ pms_date($investor->created_at) }}</dd>
                    </dl>
                    @if ($investor->notes)
                        <hr><div class="small text-muted">{{ $investor->notes }}</div>
                    @endif
                </div>
            </div>

            @can('update', $investor)
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Status</div>
                    <div class="card-body">
                        @if ($investor->hasUnsettledInvestment())
                            <div class="alert alert-warning small mb-2">
                                This investor still holds capital in a property that has not been sold or settled,
                                so they cannot be deactivated yet.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('investors.status', $investor) }}" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="status" value="{{ $investor->isActive() ? 'inactive' : 'active' }}">
                            <button class="btn btn-sm {{ $investor->isActive() ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                    @disabled($investor->isActive() && $investor->hasUnsettledInvestment())>
                                {{ $investor->isActive() ? 'Deactivate' : 'Reactivate' }}
                            </button>
                        </form>
                        <div class="form-text mt-2">Deactivating never removes any history.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Record capital received</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('investors.capital', $investor) }}" class="row g-2">
                            @csrf
                            <div class="col-6">
                                <label class="form-label small mb-1 required">Amount</label>
                                <input name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 required">Date</label>
                                <input name="received_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1 required">Into account</label>
                                <select name="financial_account_id" class="form-select form-select-sm" required>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-primary">Record receipt</button></div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white fw-semibold">Investor portal</div>
                    <div class="card-body">
                        @if (! $portalAllowed)
                            <div class="text-muted small">The investor portal is not enabled on your plan.</div>
                        @else
                            <form method="POST" action="{{ route('investors.portal', $investor) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="enabled" value="{{ $investor->portal_enabled ? 0 : 1 }}">
                                @unless ($investor->portal_enabled)
                                    <div class="col-12">
                                        <label class="form-label small mb-1 required">Login email</label>
                                        <input name="email" type="email" value="{{ $investor->email }}" class="form-control form-control-sm" required>
                                    </div>
                                @endunless
                                <div class="col-12">
                                    <button class="btn btn-sm {{ $investor->portal_enabled ? 'btn-outline-danger' : 'btn-outline-primary' }}">
                                        {{ $investor->portal_enabled ? 'Switch portal access off' : 'Give portal access' }}
                                    </button>
                                </div>
                            </form>
                            <div class="form-text mt-2">
                                An investor with portal access sees only their own investments and statement.
                            </div>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
