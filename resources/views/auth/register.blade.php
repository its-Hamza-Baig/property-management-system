@extends('layouts.guest')
@section('title', 'Request an account')

@section('content')
    <p class="text-muted small">
        Fill this in and confirm your email address. An administrator then reviews
        and activates your workspace, and you will be able to sign in.
    </p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="company_name" class="form-label required">Business name</label>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}"
                   class="form-control @error('company_name') is-invalid @enderror" required autofocus>
            <div class="form-text">This becomes the name of your workspace.</div>
            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="name" class="form-label required">Your full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label required">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone number</label>
            <input id="phone" name="phone" type="text" value="{{ old('phone') }}"
                   class="form-control @error('phone') is-invalid @enderror">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label required">Password</label>
            <input id="password" name="password" type="password"
                   class="form-control @error('password') is-invalid @enderror" required>
            <div class="form-text">At least 10 characters, including letters and numbers.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label required">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Send request</button>
    </form>
@endsection

@section('footer')
    Already have an account? <a href="{{ route('login') }}">Sign in</a>
@endsection
