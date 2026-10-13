@extends('layouts.app')
@section('title', 'Expenses')
@section('subheading', 'An expense is recorded when it is incurred. Paying it later settles the payable and is not a second expense.')

@section('actions')
    @can('create', App\Models\Expense::class)
        <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add expense</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Property</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>{{ $property->reference }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="fw-semibold">Expenses</span>
            <span>Confirmed total <strong class="money">{{ pkr($confirmedTotal) }}</strong></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Category</th><th>Treatment</th><th>Property</th><th>Status</th><th>Payment</th><th class="text-end">Amount</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ pms_date($expense->expense_date) }}</td>
                        <td><a href="{{ route('expenses.show', $expense) }}">{{ $expense->category->name }}</a></td>
                        <td><span class="badge {{ $expense->is_capitalized ? 'bg-info text-dark' : 'bg-light text-dark' }}">
                            {{ $expense->is_capitalized ? 'Added to cost' : 'Period expense' }}</span></td>
                        <td>{{ $expense->property?->reference ?: ($expense->sale?->property?->reference ?: '-') }}</td>
                        <td>{{ $expense->statusLabel() }}</td>
                        <td class="small">{{ $expense->paymentStatusLabel() }}</td>
                        <td class="text-end money">{{ pkr($expense->amount, false) }}</td>
                        <td class="text-end"><a href="{{ route('expenses.show', $expense) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No expenses recorded for this filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $expenses->links() }}</div>
@endsection
