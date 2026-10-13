@extends('layouts.app')
@section('title', 'Reservation')
@section('heading', 'Reservation of '.$reservation->property->reference)
@section('subheading', $reservation->buyer->name.' · '.pms_date($reservation->reservation_date))

@section('actions')
    @can('update', $reservation)
        <a href="{{ route('reservations.edit', $reservation) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Status</div><div><span class="badge fs-6 bg-secondary">{{ $reservation->statusLabel() }}</span></div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Token agreed</div><div class="kpi-value money">{{ pkr($reservation->token_amount, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Received (held as advance)</div><div class="kpi-value money">{{ pkr($received, false) }}</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card kpi-card"><div class="card-body">
            <div class="kpi-label">Expires</div><div class="kpi-value">{{ pms_date($reservation->expiry_date) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Receipts</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Account</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        @forelse ($reservation->payments as $payment)
                            <tr class="{{ $payment->status === 'reversed' ? 'text-muted' : '' }}">
                                <td>{{ pms_date($payment->payment_date) }}</td>
                                <td>{{ $payment->typeLabel() }}</td>
                                <td>{{ $payment->financialAccount->name }}</td>
                                <td class="text-end money">{{ pkr($payment->amount, false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">No money received against this reservation.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.documents', [
                'documents' => $reservation->documents,
                'ownerType' => 'reservation',
                'ownerId' => $reservation->id,
                'defaultCategory' => 'receipt',
            ])
        </div>

        <div class="col-lg-5">
            @if ($reservation->isActive())
                @can('update', $reservation)
                    <div class="card mb-3">
                        <div class="card-header bg-white fw-semibold">Convert to a sale</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('reservations.convert', $reservation) }}" class="row g-2">
                                @csrf
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Sale price</label>
                                    <input name="sale_price" type="number" step="0.01" min="0.01"
                                           value="{{ $reservation->agreed_price }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Agreement date</label>
                                    <input name="agreement_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-sm btn-primary">Create draft sale</button>
                                    <div class="form-text">The token already received counts towards the sale.</div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white fw-semibold">Cancel the reservation</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label small mb-1 required">Reason</label>
                                    <textarea name="cancellation_reason" rows="2" class="form-control form-control-sm" required minlength="5"></textarea>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1 required">Date</label>
                                    <input name="cancellation_date" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Charge kept</label>
                                    <input name="forfeited_amount" type="number" step="0.01" min="0" max="{{ $received->toDecimal() }}"
                                           value="0" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Refunded now</label>
                                    <input name="refund_amount" type="number" step="0.01" min="0" max="{{ $received->toDecimal() }}"
                                           value="0" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Refund from</label>
                                    <select name="financial_account_id" class="form-select form-select-sm">
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-sm btn-outline-danger">Cancel reservation</button>
                                    <div class="form-text">
                                        The amount kept becomes income; the rest stays owed back to the buyer until refunded.
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan
            @else
                <div class="card">
                    <div class="card-header bg-white fw-semibold">Settlement</div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-6">Cancelled on</dt><dd class="col-6">{{ pms_date($reservation->cancellation_date) }}</dd>
                            <dt class="col-6">Charge kept</dt><dd class="col-6 money">{{ pkr($reservation->forfeited_amount, false) }}</dd>
                            <dt class="col-6">Refunded</dt><dd class="col-6 money">{{ pkr($reservation->refunded_amount, false) }}</dd>
                        </dl>
                        @if ($reservation->cancellation_reason)
                            <hr><div class="small text-muted">{{ $reservation->cancellation_reason }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
