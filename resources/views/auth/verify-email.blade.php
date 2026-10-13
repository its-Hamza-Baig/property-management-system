@extends('layouts.guest')
@section('title', 'Confirm your email address')

@section('content')
    <p>
        We have sent a confirmation link to
        <strong>{{ auth()->user()->email }}</strong>.
        Please open it to confirm the address.
    </p>
    <p class="text-muted small">
        After that your request goes to an administrator for activation. You will
        be able to sign in once the account has been approved.
    </p>

    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary">Send the email again</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary">Sign out</button>
        </form>
    </div>
@endsection
