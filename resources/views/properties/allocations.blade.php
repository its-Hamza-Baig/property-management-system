@extends('layouts.app')
@section('title', 'Investor allocations')
@section('heading', 'Investors on '.$property->reference)
@section('subheading', 'Total cost base '.pkr($totalCost).'. Investor capital may not exceed this; whatever is left belongs to the account holder.')

@section('actions')
    <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-outline-secondary">Back to property</a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Current allocation</div>
                <div class="card-body">
                    @if ($property->isSold())
                        <div class="alert alert-secondary small mb-3">
                            This property has been sold, so its allocation is frozen. The ratios used for the sale are kept with it.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('properties.allocations.update', $property) }}">
                        @csrf @method('PUT')

                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="allocation-rows">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:38%">Investor</th>
                                        <th>Capital</th>
                                        <th>Ownership %</th>
                                        <th>Profit share %</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                    $existing = $allocations->where('is_owner', false)->values();
                                    $rowCount = max($existing->count(), 1);
                                @endphp
                                @for ($i = 0; $i < $rowCount + 2; $i++)
                                    @php $row = $existing[$i] ?? null; @endphp
                                    <tr>
                                        <td>
                                            <select name="rows[{{ $i }}][investor_id]" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach ($investors as $investor)
                                                    <option value="{{ $investor->id }}" @selected($row?->investor_id === $investor->id)>{{ $investor->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input name="rows[{{ $i }}][capital_amount]" type="number" step="0.01" min="0"
                                                   value="{{ $row?->capital_amount }}" class="form-control form-control-sm"></td>
                                        <td><input name="rows[{{ $i }}][ownership_percentage]" type="number" step="0.0001" min="0" max="100"
                                                   value="{{ $row ? rtrim(rtrim($row->ownership_percentage, '0'), '.') : '' }}"
                                                   class="form-control form-control-sm" placeholder="auto"></td>
                                        <td><input name="rows[{{ $i }}][profit_share_percentage]" type="number" step="0.0001" min="0" max="100"
                                                   value="{{ $row ? rtrim(rtrim($row->profit_share_percentage, '0'), '.') : '' }}"
                                                   class="form-control form-control-sm" placeholder="auto"></td>
                                        <td class="text-muted small">{{ $row ? pms_date($row->effective_from) : '' }}</td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                        </div>

                        <div class="form-text mb-3">
                            Leave a percentage blank to use the capital ratio. Ownership and profit sharing are
                            separate agreements: set them differently if that is what was agreed. Each must total 100
                            percent once the account holder share is added.
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1 required">Effective from</label>
                                <input type="date" name="effective_from" value="{{ old('effective_from', now()->toDateString()) }}"
                                       class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Reason for the change</label>
                                <input type="text" name="reason" class="form-control form-control-sm" value="{{ old('reason') }}">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary btn-sm w-100" @disabled($property->isSold())>Save allocation</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Transfer or exit a share</div>
                <div class="card-body">
                    <p class="text-muted small">
                        Moving a share between parties is a settlement between them. It is never recorded as a
                        property sale and never touches realised profit.
                    </p>
                    <form method="POST" action="{{ route('properties.transfer-share', $property) }}" class="row g-2">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label small mb-1">From</label>
                            <select name="from_investor_id" class="form-select form-select-sm">
                                <option value="">Account holder</option>
                                @foreach ($investors as $investor)
                                    <option value="{{ $investor->id }}">{{ $investor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">To</label>
                            <select name="to_investor_id" class="form-select form-select-sm">
                                <option value="">Account holder</option>
                                @foreach ($investors as $investor)
                                    <option value="{{ $investor->id }}">{{ $investor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1 required">Date</label>
                            <input type="date" name="transfer_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1 required">Ownership %</label>
                            <input type="number" name="ownership_percentage" step="0.0001" min="0.0001" max="100" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Profit share %</label>
                            <input type="number" name="profit_share_percentage" step="0.0001" min="0" max="100" class="form-control form-control-sm" placeholder="same">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Capital moved</label>
                            <input type="number" name="capital_amount" step="0.01" min="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Settlement paid</label>
                            <input type="number" name="settlement_amount" step="0.01" min="0" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">From account</label>
                            <select name="financial_account_id" class="form-select form-select-sm">
                                <option value="">Not paid yet</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Reason</label>
                            <input type="text" name="reason" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-outline-primary" @disabled($property->isSold())>Record transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Previous versions</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Participant</th><th class="text-end">Capital</th><th class="text-end">Own %</th><th>Period</th></tr></thead>
                        <tbody>
                        @forelse ($history as $row)
                            <tr>
                                <td>{{ $row->participantName() }}</td>
                                <td class="text-end money">{{ pkr($row->capital_amount, false) }}</td>
                                <td class="text-end">{{ rtrim(rtrim($row->ownership_percentage, '0'), '.') }}</td>
                                <td class="small text-muted">{{ pms_date($row->effective_from, 'd M y') }} to {{ pms_date($row->effective_to, 'd M y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No earlier versions.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Share transfers</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>From</th><th>To</th><th class="text-end">%</th><th class="text-end">Settlement</th></tr></thead>
                        <tbody>
                        @forelse ($transfers as $transfer)
                            <tr>
                                <td>{{ pms_date($transfer->transfer_date) }}</td>
                                <td>{{ $transfer->fromName() }}</td>
                                <td>{{ $transfer->toName() }}</td>
                                <td class="text-end">{{ rtrim(rtrim($transfer->ownership_percentage, '0'), '.') }}</td>
                                <td class="text-end money">{{ pkr($transfer->settlement_amount, false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">No transfers recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
