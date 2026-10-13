@extends('layouts.app')
@section('title', $property->exists ? 'Edit property' : 'Add property')

@section('content')
    <form method="POST" action="{{ $property->exists ? route('properties.update', $property) : route('properties.store') }}">
        @csrf
        @if ($property->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Identification</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Reference</label>
                            <input name="reference" value="{{ old('reference', $property->reference) }}"
                                   class="form-control @error('reference') is-invalid @enderror" required>
                            <div class="form-text">Your own code for this property, e.g. DHA-P-114.</div>
                            @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Title</label>
                            <input name="title" value="{{ old('title', $property->title) }}"
                                   class="form-control @error('title') is-invalid @enderror" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Project or society</label>
                            <input name="project_name" value="{{ old('project_name', $property->project_name) }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Block or sector</label>
                            <input name="block" value="{{ old('block', $property->block) }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plot number</label>
                            <input name="plot_number" value="{{ old('plot_number', $property->plot_number) }}" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input name="city" value="{{ old('city', $property->city) }}" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Address</label>
                            <input name="address" value="{{ old('address', $property->address) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Details</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Type</label>
                            <select name="property_type" class="form-select" required>
                                @foreach (App\Models\Property::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('property_type', $property->property_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Size</label>
                            <input name="size" type="number" step="0.0001" min="0"
                                   value="{{ old('size', $property->size) }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Size unit</label>
                            <input name="size_unit" value="{{ old('size_unit', $property->size_unit) }}"
                                   class="form-control" placeholder="marla, kanal, sq ft">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $property->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Status</div>
                    <div class="card-body">
                        <select name="status" class="form-select">
                            @foreach ([
                                'available' => 'Available',
                                'reserved' => 'Reserved',
                                'under_sale' => 'Under sale or transfer',
                                'sold' => 'Sold',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $property->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text mt-2">
                            Status normally changes by itself as you reserve, sell and finalise.
                            Sold properties stop counting towards your plan limit.
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary">{{ $property->exists ? 'Save changes' : 'Add property' }}</button>
                    <a href="{{ $property->exists ? route('properties.show', $property) : route('properties.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection
