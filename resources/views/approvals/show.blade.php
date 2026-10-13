@extends('layouts.app')
@section('title', 'Approval request')
@section('heading', $request->actionLabel())

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge {{ $request->statusBadgeClass() }}">{{ $request->statusLabel() }}</span></dd>

                        <dt class="col-sm-4">Amount</dt>
                        <dd class="col-sm-8 money">{{ pkr($request->amount) }}</dd>

                        <dt class="col-sm-4">Requested by</dt>
                        <dd class="col-sm-8">{{ $request->requester?->name }} on {{ pms_date($request->created_at, 'd M Y H:i') }}</dd>

                        @if ($request->request_note)
                            <dt class="col-sm-4">Note</dt>
                            <dd class="col-sm-8">{{ $request->request_note }}</dd>
                        @endif

                        @if ($request->approver)
                            <dt class="col-sm-4">Decided by</dt>
                            <dd class="col-sm-8">{{ $request->approver->name }} on {{ pms_date($request->decided_at, 'd M Y H:i') }}</dd>
                        @endif

                        @if ($request->decision_reason)
                            <dt class="col-sm-4">Reason given</dt>
                            <dd class="col-sm-8">{{ $request->decision_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @can('decide', $request)
                <div class="card">
                    <div class="card-header bg-white fw-semibold">Your decision</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('approvals.approve', $request) }}">
                                    @csrf
                                    <label class="form-label small mb-1">Note (optional)</label>
                                    <textarea name="reason" rows="2" class="form-control form-control-sm mb-2"></textarea>
                                    <button class="btn btn-success w-100">Approve</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('approvals.reject', $request) }}">
                                    @csrf
                                    <label class="form-label small mb-1 required">Reason</label>
                                    <textarea name="reason" rows="2" class="form-control form-control-sm mb-2" required minlength="5"></textarea>
                                    <button class="btn btn-outline-danger w-100">Reject</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif ($request->isPending())
                <div class="alert alert-secondary">
                    @if ($request->requested_by === auth()->id())
                        You raised this request, so somebody else has to decide it.
                    @else
                        You do not have permission to decide approval requests.
                    @endif
                </div>
            @endcan
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white fw-semibold">What is waiting</div>
                <div class="card-body small">
                    @php $subject = $request->approvable; @endphp
                    @if ($subject instanceof \App\Models\PropertyPurchase)
                        Purchase of <a href="{{ route('purchases.show', $subject) }}">{{ $subject->property->reference }}</a>
                        for {{ pkr($subject->purchase_price) }}.
                    @elseif ($subject instanceof \App\Models\Sale)
                        Finalising the sale of <a href="{{ route('sales.show', $subject) }}">{{ $subject->property->reference }}</a>
                        at {{ pkr($subject->sale_price) }}.
                    @elseif ($subject instanceof \App\Models\Expense)
                        Expense <a href="{{ route('expenses.show', $subject) }}">{{ $subject->category->name }}</a>
                        of {{ pkr($subject->amount) }}.
                    @elseif ($subject instanceof \App\Models\InvestorProfitPayable)
                        Payout to {{ $subject->participantName() }} against
                        <a href="{{ route('sales.show', $subject->sale) }}">{{ $subject->sale->property->reference }}</a>.
                    @else
                        The related record is no longer available.
                    @endif

                    <hr>
                    <div class="text-muted">
                        Until this is approved the operation cannot be completed, and no financial entry has been posted.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
