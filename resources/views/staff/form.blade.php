@extends('layouts.app')
@section('title', $staff->exists ? 'Edit staff member' : 'Add staff member')

@section('content')
    <form method="POST" action="{{ $staff->exists ? route('staff.update', $staff) : route('staff.store') }}">
        @csrf
        @if ($staff->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Person</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input name="name" value="{{ old('name', $staff->name) }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Email</label>
                            <input name="email" type="email" value="{{ old('email', $staff->email) }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input name="phone" value="{{ old('phone', $staff->phone) }}" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label {{ $staff->exists ? '' : 'required' }}">Password</label>
                            <input name="password" type="password" class="form-control" @required(! $staff->exists)>
                            <div class="form-text">
                                {{ $staff->exists ? 'Leave blank to keep the current password.' : 'At least 10 characters with letters and numbers.' }}
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label {{ $staff->exists ? '' : 'required' }}">Confirm password</label>
                            <input name="password_confirmation" type="password" class="form-control" @required(! $staff->exists)>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Quick preset</div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="preset" value="admin" id="preset-admin">
                            <label class="form-check-label" for="preset-admin">
                                <strong>Admin</strong> — everything except workspace settings
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="preset" value="staff" id="preset-staff">
                            <label class="form-check-label" for="preset-staff">
                                <strong>Staff</strong> — day to day recording, no finalising or approving
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="preset" value="custom" id="preset-custom" checked>
                            <label class="form-check-label" for="preset-custom">
                                <strong>Custom</strong> — use the list on the right
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary">{{ $staff->exists ? 'Save changes' : 'Add staff member' }}</button>
                    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white fw-semibold">Permissions</div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Every one of these is checked on the server for every action, not just hidden from the menu.
                        </p>
                        @foreach ($groups as $group => $permissions)
                            <div class="mb-3">
                                <div class="fw-semibold small text-uppercase text-muted mb-1">{{ $group }}</div>
                                @foreach ($permissions as $key => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]"
                                               value="{{ $key }}" id="perm-{{ $key }}"
                                               @checked(in_array($key, old('permissions', $selected), true))>
                                        <label class="form-check-label" for="perm-{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
