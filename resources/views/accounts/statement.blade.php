@extends('layouts.app')
@section('title', 'Account statement')
@section('heading', $account->displayName())
@section('subheading', 'Current balance '.pkr($balance))

@section('actions')
    <a href="{{ route('accounts.index') }}" class="btn btn-sm btn-outline-secondary">Back to accounts</a>
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Apply</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Description</th><th>Property</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr>
                </thead>
                <tbody>
                @php $running = \App\Support\Money::zero(); @endphp
                @forelse ($entries as $entry)
                    @php
                        $in = \App\Support\Money::of($entry->debit);
                        $out = \App\Support\Money::of($entry->credit);
                        $running = $running->plus($in)->minus($out);
                    @endphp
                    <tr>
                        <td>{{ pms_date($entry->journal->journal_date) }}</td>
                        <td>
                            {{ $entry->journal->memo }}
                            @if ($entry->journal->reversal_of_id)<span class="badge bg-secondary">Reversal</span>@endif
                        </td>
                        <td class="small">{{ $entry->property?->reference ?: '-' }}</td>
                        <td class="text-end money">{{ $in->isZero() ? '' : pkr($in, false) }}</td>
                        <td class="text-end money">{{ $out->isZero() ? '' : pkr($out, false) }}</td>
                        <td class="text-end {{ money_class($running) }}">{{ pkr($running, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No movements on this account in the selected period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
