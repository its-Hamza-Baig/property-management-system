@extends('layouts.guest')
@section('title', 'Choose a new password')

@section('content')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label required">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label required">New password</label>
            <input id="password" name="password" type="password"
                   class="form-control @error('password') is-invalid @enderror" required autofocus>
            <div class="form-text">At least 10 characters, including letters and numbers.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label required">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100">Save the new password</button>
    </form>
@endsection
