@extends('layouts.app')
@section('title', 'My investments')
@section('subheading', 'Current and past holdings, including the periods each one applied to.')

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Property</th><th>Status</th><th class="text-end">Capital</th>
                        <th class="text-end">Ownership %</th><th class="text-end">Profit share %</th><th>Period</th></tr>
                </thead>
                <tbody>
                @forelse ($allocations as $allocation)
                    <tr class="{{ $allocation->effective_to ? 'text-muted' : '' }}">
                        <td>{{ $allocation->property->reference }} — {{ $allocation->property->title }}</td>
                        <td><span class="badge {{ $allocation->property->statusBadgeClass() }}">{{ $allocation->property->statusLabel() }}</span></td>
                        <td class="text-end money">{{ pkr($allocation->capital_amount, false) }}</td>
                        <td class="text-end">{{ rtrim(rtrim($allocation->ownership_percentage, '0'), '.') }}</td>
                        <td class="text-end">{{ rtrim(rtrim($allocation->profit_share_percentage, '0'), '.') }}</td>
                        <td class="small">
                            {{ pms_date($allocation->effective_from, 'd M y') }} —
                            {{ $allocation->effective_to ? pms_date($allocation->effective_to, 'd M y') : 'current' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No investments recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
