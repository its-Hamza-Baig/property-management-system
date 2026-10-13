@extends('layouts.app')
@section('title', 'Reservations')
@section('subheading', 'A token is held as a buyer advance until the sale is finalised. It is never treated as profit.')

@section('actions')
    @can('create', App\Models\Reservation::class)
        <a href="{{ route('reservations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New reservation</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach (['active' => 'Active', 'converted' => 'Converted', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $v => $l)
                        <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Property</th><th>Buyer</th><th>Expires</th><th>Status</th><th class="text-end">Token</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>{{ pms_date($reservation->reservation_date) }}</td>
                        <td><a href="{{ route('reservations.show', $reservation) }}" class="fw-semibold">{{ $reservation->property->reference }}</a></td>
                        <td>{{ $reservation->buyer->name }}</td>
                        <td class="{{ $reservation->isExpired() ? 'text-danger' : '' }}">{{ pms_date($reservation->expiry_date) }}</td>
                        <td>{{ $reservation->statusLabel() }}</td>
                        <td class="text-end money">{{ pkr($reservation->token_amount, false) }}</td>
                        <td class="text-end"><a href="{{ route('reservations.show', $reservation) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No reservations recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reservations->links() }}</div>
@endsection
