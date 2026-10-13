@extends('layouts.app')
@section('title', 'My statement')
@section('subheading', 'Profit calculated on each closed sale, what has been paid to you, and what is still outstanding.')

@section('content')
    <div class="alert alert-info">
        Still owed to you: <strong class="money">{{ pkr($profitOutstanding) }}</strong>.
        Profit calculated on a sale is not the same as cash. When it is paid is decided by the account holder
        according to your agreement.
    </div>

    @forelse ($payables as $payable)
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ $payable->sale->property->reference }} — {{ $payable->sale->property->title }}</span>
                <span class="badge {{ $payable->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $payable->statusLabel() }}</span>
            </div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-7">Sale finalised</dt><dd class="col-5 text-end">{{ pms_date($payable->sale->finalized_at) }}</dd>
                    <dt class="col-7">Your profit share</dt><dd class="col-5 text-end">{{ rtrim(rtrim($payable->profit_share_percentage, '0'), '.') }}%</dd>
                    <dt class="col-7">Profit calculated</dt>
                    <dd class="col-5 text-end {{ money_class($payable->profit_amount) }}">{{ pkr($payable->profit_amount, false) }}</dd>
                    <dt class="col-7">Paid to you</dt><dd class="col-5 text-end money">{{ pkr($payable->paid_amount, false) }}</dd>
                    <dt class="col-7 fw-semibold">Outstanding</dt>
                    <dd class="col-5 text-end fw-semibold {{ money_class($payable->outstanding()) }}">{{ pkr($payable->outstanding(), false) }}</dd>
                </dl>

                @if ($payable->distributions->where('status', 'confirmed')->isNotEmpty())
                    <hr>
                    <div class="small fw-semibold mb-1">Payments received</div>
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        @foreach ($payable->distributions->where('status', 'confirmed') as $distribution)
                            <tr>
                                <td>{{ pms_date($distribution->payment_date) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $distribution->method)) }}</td>
                                <td>{{ $distribution->reference ?: '-' }}</td>
                                <td class="text-end money">{{ pkr($distribution->amount, false) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body empty-state">
            No sales involving your investments have been finalised yet.
        </div></div>
    @endforelse
@endsection
