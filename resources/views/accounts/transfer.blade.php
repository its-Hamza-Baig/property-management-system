@extends('layouts.app')
@section('title', 'Transfer between accounts')
@section('subheading', 'Moving your own money. This is recorded once and is never counted as a sale, a receipt or an expense.')

@section('content')
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('transfers.store') }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <div class="mb-3">
                            <label class="form-label required">From account</label>
                            <select name="from_financial_account_id" class="form-select" required>
                                <option value="">Choose an account</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('from_financial_account_id') == $account->id)>
                                        {{ $account->displayName() }} — {{ pkr($account->currentBalance()) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">To account</label>
                            <select name="to_financial_account_id" class="form-select" required>
                                <option value="">Choose an account</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('to_financial_account_id') == $account->id)>
                                        {{ $account->displayName() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">Amount</label>
                                <input name="amount" type="number" step="0.01" min="0.01"
                                       value="{{ old('amount') }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Date</label>
                                <input name="transfer_date" type="date" value="{{ old('transfer_date', now()->toDateString()) }}"
                                       class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Reference</label>
                                <input name="reference" value="{{ old('reference') }}" class="form-control"
                                       placeholder="Slip number, cheque number or similar">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-primary">Record transfer</button>
                            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
