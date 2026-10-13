@extends('layouts.app')
@section('title', $reservation->exists ? 'Edit reservation' : 'New reservation')

@section('content')
    <form method="POST" action="{{ $reservation->exists ? route('reservations.update', $reservation) : route('reservations.store') }}">
        @csrf
        @if ($reservation->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body row g-3">
                        @unless ($reservation->exists)
                            <div class="col-md-6">
                                <label class="form-label required">Property</label>
                                <select name="property_id" class="form-select" required>
                                    <option value="">Choose a property</option>
                                    @foreach ($properties as $property)
                                        <option value="{{ $property->id }}"
                                            @selected(old('property_id', $preset['property_id'] ?? null) == $property->id)>
                                            {{ $property->reference }} — {{ $property->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Buyer</label>
                                <select name="buyer_id" class="form-select" required>
                                    <option value="">Choose a buyer</option>
                                    @foreach ($buyers as $buyer)
                                        <option value="{{ $buyer->id }}" @selected(old('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Reservation date</label>
                                <input name="reservation_date" type="date" value="{{ old('reservation_date', now()->toDateString()) }}"
                                       class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Token amount</label>
                                <input name="token_amount" type="number" step="0.01" min="0"
                                       value="{{ old('token_amount', '0') }}" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Received into</label>
                                <select name="financial_account_id" class="form-select">
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless

                        <div class="col-md-6">
                            <label class="form-label">Agreed price</label>
                            <input name="agreed_price" type="number" step="0.01" min="0"
                                   value="{{ old('agreed_price', $reservation->agreed_price) }}" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry date</label>
                            <input name="expiry_date" type="date"
                                   value="{{ old('expiry_date', optional($reservation->expiry_date)->toDateString()) }}" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="2" class="form-control">{{ old('notes', $reservation->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card"><div class="card-body">
                    <div class="alert alert-info small">
                        The token is received into a cash or bank account and held as a buyer advance, which is a
                        liability. It only becomes part of the sale when the sale is finalised.
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary">{{ $reservation->exists ? 'Save changes' : 'Create reservation' }}</button>
                        <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div></div>
            </div>
        </div>
    </form>
@endsection
