@extends('layouts.app')
@section('title', 'Cash and bank')
@section('subheading', 'Tracks the money of the business. Transfers between your own accounts are never income or expense.')

@section('actions')
    <div class="btn-group">
        <a href="{{ route('transfers.create') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-right"></i> Transfer</a>
        @can('create', App\Models\FinancialAccount::class)
            <a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add account</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="fw-semibold">Accounts</span>
            <span>Total available <strong class="money">{{ pkr($total) }}</strong></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Account</th><th>Type</th><th class="text-end">Opening</th><th class="text-end">Current balance</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td class="fw-semibold">{{ $account->displayName() }}</td>
                        <td>{{ $account->typeLabel() }}</td>
                        <td class="text-end money">{{ pkr($account->opening_balance, false) }}</td>
                        <td class="text-end {{ money_class($balances[$account->id]) }}">{{ pkr($balances[$account->id], false) }}</td>
                        <td>
                            <span class="badge {{ $account->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('accounts.statement', $account) }}" class="btn btn-sm btn-outline-secondary">Statement</a>
                            @can('update', $account)
                                <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">
                        No accounts yet. <a href="{{ route('accounts.create') }}">Add a cash or bank account</a> before recording payments.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold">Recent transfers</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Date</th><th>From</th><th>To</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td>{{ pms_date($transfer->transfer_date) }}</td>
                        <td>{{ $transfer->fromAccount->name }}</td>
                        <td>{{ $transfer->toAccount->name }}</td>
                        <td>{{ $transfer->reference ?: '-' }}</td>
                        <td class="text-end money">{{ pkr($transfer->amount, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">No transfers recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
