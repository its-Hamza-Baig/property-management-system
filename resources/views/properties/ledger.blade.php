@extends('layouts.app')
@section('title', 'Property ledger')
@section('heading', 'Ledger for '.$property->reference)
@section('subheading', 'Every entry that touched this property, in the order it was posted.')

@section('actions')
    <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-outline-secondary">Back to property</a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Apply</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ pms_date($entry->journal->journal_date) }}</td>
                        <td>
                            {{ $entry->journal->memo }}
                            @if ($entry->journal->is_adjustment)
                                <span class="badge bg-warning text-dark">Correction</span>
                            @endif
                            @if ($entry->journal->reversal_of_id)
                                <span class="badge bg-secondary">Reversal</span>
                            @endif
                            @if ($entry->journal->reason)
                                <div class="text-muted small">{{ $entry->journal->reason }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $entry->ledgerAccount->name }}</td>
                        <td class="text-end money">{{ \App\Support\Money::of($entry->debit)->isZero() ? '' : pkr($entry->debit, false) }}</td>
                        <td class="text-end money">{{ \App\Support\Money::of($entry->credit)->isZero() ? '' : pkr($entry->credit, false) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Nothing posted against this property yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $entries->links() }}</div>
@endsection
