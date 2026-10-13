@extends('layouts.app')
@section('title', 'Reports')
@section('subheading', 'Every report is limited to your own workspace and to what your permissions allow.')

@section('content')
    <div class="row g-3">
        @foreach ($reports as $key => $report)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <h3 class="h6">{{ $report['title'] }}</h3>
                        <p class="text-muted small flex-grow-1">{{ $report['description'] }}</p>
                        <a href="{{ route('reports.show', $key) }}" class="btn btn-sm btn-outline-primary">Open report</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
