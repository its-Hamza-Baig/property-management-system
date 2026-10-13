@extends('layouts.app')
@section('title', $expense->exists ? 'Edit draft expense' : 'Add expense')
@section('subheading', 'Saving creates a draft. The cost is recorded when you confirm it.')

@section('content')
    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Expense</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Category</label>
                            <select name="expense_category_id" class="form-select" required>
                                <option value="">Choose a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>
                                        {{ $category->name }} ({{ $category->is_capitalized ? 'adds to property cost' : 'period expense' }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                A capitalised category increases the cost base of the property instead of reducing profit now.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Date</label>
                            <input name="expense_date" type="date"
                                   value="{{ old('expense_date', optional($expense->expense_date)->toDateString() ?? now()->toDateString()) }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Amount</label>
                            <input name="amount" type="number" step="0.01" min="0.01"
                                   value="{{ old('amount', $expense->amount) }}" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Property</label>
                            <select name="property_id" class="form-select">
                                <option value="">Not property specific</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}"
                                        @selected(old('property_id', $expense->property_id ?? ($preset['property_id'] ?? null)) == $property->id)>
                                        {{ $property->reference }} — {{ $property->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Linked sale</label>
                            <select name="sale_id" class="form-select">
                                <option value="">Not a sale expense</option>
                                @foreach ($sales as $sale)
                                    <option value="{{ $sale->id }}"
                                        @selected(old('sale_id', $expense->sale_id ?? ($preset['sale_id'] ?? null)) == $sale->id)>
                                        {{ $sale->property->reference }} — {{ pms_date($sale->agreement_date) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Sale expenses are deducted when that sale is finalised.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input name="description" value="{{ old('description', $expense->description) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Whose capital does this represent?</div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Optional, and separate from who actually hands over the money. You record the actual
                            payment afterwards, from whichever account it came out of.
                        </p>
                        <table class="table table-sm">
                            <thead class="table-light"><tr><th style="width:30%">Funded by</th><th style="width:40%">Investor</th><th>Amount</th></tr></thead>
                            <tbody>
                            @php $sources = $expense->exists ? $expense->fundingSources->values() : collect(); @endphp
                            @for ($i = 0; $i < max($sources->count(), 1) + 1; $i++)
                                @php $source = $sources[$i] ?? null; @endphp
                                <tr>
                                    <td>
                                        <select name="funding[{{ $i }}][funder_type]" class="form-select form-select-sm">
                                            <option value="account_holder" @selected($source?->funder_type === 'account_holder')>Account holder</option>
                                            <option value="investor" @selected($source?->funder_type === 'investor')>Investor</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="funding[{{ $i }}][investor_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach ($investors as $investor)
                                                <option value="{{ $investor->id }}" @selected($source?->investor_id === $investor->id)>{{ $investor->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input name="funding[{{ $i }}][amount]" type="number" step="0.01" min="0"
                                               value="{{ $source?->amount }}" class="form-control form-control-sm"></td>
                                </tr>
                            @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info small">
                            <strong>Recognition and payment are separate.</strong>
                            Confirming records the cost and creates a payable. You then record payments against
                            that payable, so the same money is never counted twice.
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary">Save draft</button>
                            <a href="{{ $expense->exists ? route('expenses.show', $expense) : route('expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
