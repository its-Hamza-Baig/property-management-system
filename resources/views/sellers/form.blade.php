@extends('layouts.app')
@section('title', $seller->exists ? 'Edit seller' : 'Add seller')

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $seller->exists ? route('sellers.update', $seller) : route('sellers.store') }}">
                        @csrf
                        @if ($seller->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input name="name" value="{{ old('name', $seller->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input name="phone" value="{{ old('phone', $seller->phone) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CNIC</label>
                                <input name="cnic" value="{{ old('cnic', $seller->cnic) }}" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Address</label>
                            <input name="address" value="{{ old('address', $seller->address) }}" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $seller->notes) }}</textarea>
                        </div>

                        <button class="btn btn-primary">{{ $seller->exists ? 'Save changes' : 'Add seller' }}</button>
                        <a href="{{ route('sellers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
