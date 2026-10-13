@extends('layouts.app')
@section('title', 'Edit plan')
@section('heading', 'Plan: '.$plan->name)

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
                @csrf @method('PUT')

                <div class="card mb-3">
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Name</label>
                            <input name="name" value="{{ old('name', $plan->name) }}" class="form-control" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked($plan->is_active)>
                                <label class="form-check-label" for="active">Plan is available</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input name="description" value="{{ old('description', $plan->description) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Limits and features</div>
                    <div class="card-body">
                        <table class="table table-sm align-middle">
                            <tbody>
                            @foreach ($definitions as $key => $definition)
                                <tr>
                                    <td>
                                        {{ $definition['label'] }}
                                        <div class="text-muted" style="font-size:.75rem">{{ $definition['help'] }}</div>
                                    </td>
                                    <td style="width:220px">
                                        @if ($definition['type'] === 'bool')
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="limits[{{ $key }}]" value="0">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                       name="limits[{{ $key }}]" @checked($limits[$key])>
                                            </div>
                                        @elseif ($definition['type'] === 'csv')
                                            <input name="limits[{{ $key }}]" class="form-control form-control-sm"
                                                   value="{{ is_array($limits[$key]) ? implode(',', $limits[$key]) : $limits[$key] }}">
                                        @else
                                            <input name="limits[{{ $key }}]" type="number" class="form-control form-control-sm"
                                                   value="{{ $limits[$key] }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <button class="btn btn-primary">Save plan</button>
                <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
