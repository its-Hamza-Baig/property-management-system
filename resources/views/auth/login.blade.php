@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label required">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label required">Password</label>
            <input id="password" name="password" type="password"
                   class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Keep me signed in</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot your password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
@endsection

@section('footer')
    Do not have an account yet? <a href="{{ route('register') }}">Request one</a>
@endsection
