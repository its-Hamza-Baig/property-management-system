@extends('layouts.app')
@section('title', 'Plans and limits')
@section('subheading', 'Free accounts today. Adding a paid tier later means adding a plan here, not rebuilding anything.')

@section('content')
    <div class="row g-3">
        @foreach ($plans as $plan)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ $plan->name }}</span>
                        <span>
                            @if ($plan->is_default)<span class="badge bg-primary">Default</span>@endif
                            <span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <p class="text-muted small">{{ $plan->description }}</p>
                        <dl class="row small mb-3">
                            @foreach ($definitions as $key => $definition)
                                @php $value = $plan->limits[$key] ?? $definition['default']; @endphp
                                <dt class="col-8 fw-normal text-muted">{{ $definition['label'] }}</dt>
                                <dd class="col-4 text-end">
                                    @if ($definition['type'] === 'bool')
                                        {{ $value ? 'Yes' : 'No' }}
                                    @elseif ((string) $value === '-1')
                                        Unlimited
                                    @else
                                        {{ is_array($value) ? implode(', ', $value) : $value }}
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="text-muted small">{{ $plan->tenants_count }} accounts</span>
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
