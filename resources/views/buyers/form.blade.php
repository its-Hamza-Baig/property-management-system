@extends('layouts.app')
@section('title', $buyer->exists ? 'Edit buyer' : 'Add buyer')

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $buyer->exists ? route('buyers.update', $buyer) : route('buyers.store') }}">
                        @csrf
                        @if ($buyer->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input name="name" value="{{ old('name', $buyer->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input name="phone" value="{{ old('phone', $buyer->phone) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input name="email" type="email" value="{{ old('email', $buyer->email) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CNIC</label>
                                <input name="cnic" value="{{ old('cnic', $buyer->cnic) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input name="address" value="{{ old('address', $buyer->address) }}" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $buyer->notes) }}</textarea>
                        </div>

                        <button class="btn btn-primary">{{ $buyer->exists ? 'Save changes' : 'Add buyer' }}</button>
                        <a href="{{ route('buyers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
