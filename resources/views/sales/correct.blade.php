@extends('layouts.app')
@section('title', 'Correct a finalised sale')
@section('heading', 'Correct the sale of '.$sale->property->reference)

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="alert alert-warning">
                <strong>This does not overwrite anything.</strong>
                The original figures stay in the audit trail, the change is posted as a separate adjustment entry,
                and amounts already paid to investors are left exactly as they are. Only what is still outstanding moves.
            </div>

            <div class="card">
                <div class="card-body">
                    <dl class="row small mb-3">
                        <dt class="col-6">Current sale price</dt><dd class="col-6 text-end money">{{ pkr($sale->sale_price) }}</dd>
                        <dt class="col-6">Cost applied at finalisation</dt><dd class="col-6 text-end money">{{ pkr($sale->allocated_cost) }}</dd>
                        <dt class="col-6">Sale expenses applied</dt><dd class="col-6 text-end money">{{ pkr($sale->sale_expense_total) }}</dd>
                        <dt class="col-6 fw-semibold">Current realised profit</dt>
                        <dd class="col-6 text-end fw-semibold {{ money_class($sale->realized_profit) }}">{{ pkr($sale->realized_profit) }}</dd>
                    </dl>

                    <form method="POST" action="{{ route('sales.correct.store', $sale) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Corrected sale price</label>
                                <input name="sale_price" type="number" step="0.01" min="0.01"
                                       value="{{ old('sale_price', $sale->sale_price) }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Correction date</label>
                                <input name="correction_date" type="date" value="{{ old('correction_date', now()->toDateString()) }}"
                                       class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label required">Reason for the correction</label>
                                <textarea name="reason" rows="3" class="form-control" required minlength="10"
                                          placeholder="Explain what was wrong and why it is being changed.">{{ old('reason') }}</textarea>
                                <div class="form-text">This is mandatory and is stored against the sale permanently.</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-warning">Record correction</button>
                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
