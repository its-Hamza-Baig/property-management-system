@extends('layouts.guest')
@section('title', 'Reset your password')

@section('content')
    <p class="text-muted small">Enter your email address and we will send you a link to set a new password.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label required">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100">Send the link</button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}">Back to sign in</a>
@endsection
