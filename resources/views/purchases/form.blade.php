@extends('layouts.app')
@section('title', $purchase->exists ? 'Edit draft purchase' : 'Record a purchase')
@section('subheading', 'Saving creates a draft. Nothing is posted to the books until you confirm it.')

@section('content')
    <form method="POST" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}">
        @csrf
        @if ($purchase->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Purchase</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Property</label>
                            <select name="property_id" class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">Choose a property</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}"
                                        @selected(old('property_id', $purchase->property_id ?? $selectedProperty) == $property->id)>
                                        {{ $property->reference }} — {{ $property->title }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Only properties without a purchase are listed.</div>
                            @error('property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Purchase date</label>
                            <input name="purchase_date" type="date"
                                   value="{{ old('purchase_date', optional($purchase->purchase_date)->toDateString() ?? now()->toDateString()) }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Purchase price</label>
                            <input name="purchase_price" type="number" step="0.01" min="0.01"
                                   value="{{ old('purchase_price', $purchase->purchase_price) }}"
                                   class="form-control @error('purchase_price') is-invalid @enderror" required>
                            @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="2" class="form-control">{{ old('notes', $purchase->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Sellers</div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Optional. If you list sellers individually, the amounts must add up to the purchase price,
                            and you then get seller-wise outstanding tracking.
                        </p>
                        <table class="table table-sm">
                            <thead class="table-light"><tr><th style="width:65%">Seller</th><th>Amount</th></tr></thead>
                            <tbody>
                            @php $shares = $purchase->exists ? $purchase->sellerShares->values() : collect(); @endphp
                            @for ($i = 0; $i < max($shares->count(), 1) + 2; $i++)
                                @php $share = $shares[$i] ?? null; @endphp
                                <tr>
                                    <td>
                                        <select name="sellers[{{ $i }}][seller_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($sellers as $seller)
                                                <option value="{{ $seller->id }}" @selected($share?->seller_id === $seller->id)>{{ $seller->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input name="sellers[{{ $i }}][amount]" type="number" step="0.01" min="0"
                                               value="{{ $share?->amount }}" class="form-control form-control-sm">
                                    </td>
                                </tr>
                            @endfor
                            </tbody>
                        </table>
                        <a href="{{ route('sellers.create') }}" class="small">Add a new seller</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="alert alert-info small mb-3">
                            <strong>What happens on confirm.</strong>
                            The purchase price is recorded as property cost and the same amount becomes a payable
                            to the sellers. Paying them later reduces that payable; it never adds the cost again.
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary">{{ $purchase->exists ? 'Save draft' : 'Save draft' }}</button>
                            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
