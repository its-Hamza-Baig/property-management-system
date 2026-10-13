@extends('layouts.app')
@section('title', 'My profile')

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Details</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Email</label>
                            <input name="email" type="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                            <div class="form-text">Changing this means confirming the new address again.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control">
                        </div>
                        <button class="btn btn-primary">Save details</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Change password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label class="form-label required">Current password</label>
                            <input name="current_password" type="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">New password</label>
                            <input name="password" type="password" class="form-control" required>
                            <div class="form-text">At least 10 characters with letters and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Confirm new password</label>
                            <input name="password_confirmation" type="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold">Notifications</div>
                <div class="card-body">
                    <p class="text-muted small">
                        In-app notices are on by default, email is opt-in. A notification only tells you something
                        happened; it never changes a financial record.
                    </p>
                    <form method="POST" action="{{ route('profile.notifications') }}">
                        @csrf @method('PUT')
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Tell me about</th><th class="text-center">In app</th><th class="text-center">Email</th></tr></thead>
                            <tbody>
                            @foreach ($types as $type => $label)
                                @php $pref = $preferences[$type] ?? null; @endphp
                                <tr>
                                    <td class="small">{{ $label }}</td>
                                    <td class="text-center">
                                        <input type="hidden" name="preferences[{{ $type }}][in_app]" value="0">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               name="preferences[{{ $type }}][in_app]" @checked($pref?->in_app ?? true)>
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="preferences[{{ $type }}][email]" value="0">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               name="preferences[{{ $type }}][email]" @checked($pref?->email ?? false)>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <button class="btn btn-primary">Save preferences</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
