@extends('layouts.guest')
@section('title', 'Account status')

@section('content')
    @php
        $status = $tenant?->status;
    @endphp

    <div class="text-center mb-3">
        <div class="display-6">
            @switch($status)
                @case(\App\Models\Tenant::STATUS_PENDING_APPROVAL) <i class="bi bi-hourglass-split text-warning"></i> @break
                @case(\App\Models\Tenant::STATUS_SUSPENDED) <i class="bi bi-pause-circle text-danger"></i> @break
                @case(\App\Models\Tenant::STATUS_REJECTED) <i class="bi bi-x-circle text-danger"></i> @break
                @default <i class="bi bi-envelope-check text-secondary"></i>
            @endswitch
        </div>
        <h3 class="h6 mt-2">{{ $tenant?->statusLabel() ?? 'No workspace' }}</h3>
    </div>

    @switch($status)
        @case(\App\Models\Tenant::STATUS_PENDING_VERIFICATION)
            <p>Please confirm your email address first. <a href="{{ route('verification.notice') }}">Open the confirmation page</a>.</p>
            @break

        @case(\App\Models\Tenant::STATUS_PENDING_APPROVAL)
            <p>Your email address is confirmed. An administrator is reviewing your request for <strong>{{ $tenant->name }}</strong>.</p>
            <p class="text-muted small">You will be able to sign in as soon as the account is activated.</p>
            @break

        @case(\App\Models\Tenant::STATUS_SUSPENDED)
            <p>The workspace <strong>{{ $tenant->name }}</strong> is currently suspended.</p>
            @if ($tenant->status_reason)
                <p class="text-muted small">Reason given: {{ $tenant->status_reason }}</p>
            @endif
            <p class="text-muted small">Your records and financial history are untouched. Please contact the administrator.</p>
            @break

        @case(\App\Models\Tenant::STATUS_REJECTED)
            <p>This account request was not approved.</p>
            @if ($tenant->status_reason)
                <p class="text-muted small">Reason given: {{ $tenant->status_reason }}</p>
            @endif
            @break

        @case(\App\Models\Tenant::STATUS_CLOSED)
            <p>This account is closed. Financial records have been preserved according to the retention policy.</p>
            @break

        @default
            <p>Your login is not linked to a workspace. Please contact the administrator.</p>
    @endswitch

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button class="btn btn-outline-secondary w-100">Sign out</button>
    </form>
@endsection
