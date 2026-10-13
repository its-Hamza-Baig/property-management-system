@extends('layouts.app')
@section('title', $investor->exists ? 'Edit investor' : 'Add investor')

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $investor->exists ? route('investors.update', $investor) : route('investors.store') }}">
                        @csrf
                        @if ($investor->exists) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label required">Name</label>
                                <input name="name" value="{{ old('name', $investor->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required autofocus>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(old('status', $investor->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $investor->status) === 'inactive')>Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input name="phone" value="{{ old('phone', $investor->phone) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input name="email" type="email" value="{{ old('email', $investor->email) }}" class="form-control">
                                <div class="form-text">Needed only if you later give this investor a portal login.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">CNIC</label>
                                <input name="cnic" value="{{ old('cnic', $investor->cnic) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input name="address" value="{{ old('address', $investor->address) }}" class="form-control">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" rows="3" class="form-control">{{ old('notes', $investor->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-primary">{{ $investor->exists ? 'Save changes' : 'Add investor' }}</button>
                            <a href="{{ $investor->exists ? route('investors.show', $investor) : route('investors.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
