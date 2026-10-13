@extends('layouts.app')
@section('title', $sale->exists ? 'Edit draft sale' : 'Create a sale')
@section('subheading', 'A draft sale moves the property to "under sale". Nothing is posted until it is finalised.')

@section('content')
    <form method="POST" action="{{ $sale->exists ? route('sales.update', $sale) : route('sales.store') }}">
        @csrf
        @if ($sale->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Property</label>
                            <select name="property_id" class="form-select" required>
                                <option value="">Choose a property</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}"
                                        @selected(old('property_id', $sale->property_id ?? ($preset['property_id'] ?? null)) == $property->id)>
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
                                    <option value="{{ $buyer->id }}" @selected(old('buyer_id', $sale->buyer_id) == $buyer->id)>{{ $buyer->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text"><a href="{{ route('buyers.create') }}">Add a new buyer</a></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Agreement date</label>
                            <input name="agreement_date" type="date"
                                   value="{{ old('agreement_date', optional($sale->agreement_date)->toDateString() ?? now()->toDateString()) }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Agreed sale price</label>
                            <input name="sale_price" type="number" step="0.01" min="0.01"
                                   value="{{ old('sale_price', $sale->sale_price) }}" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $sale->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info small">
                            After saving you can set the installment plan, record the token and receipts, add sale
                            expenses, and then finalise. Finalising is what calculates the profit and splits it
                            between the investors.
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary">Save draft sale</button>
                            <a href="{{ $sale->exists ? route('sales.show', $sale) : route('sales.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
