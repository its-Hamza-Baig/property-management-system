@extends('layouts.app')
@section('title', 'Buyers')
@section('subheading', 'People and parties you sell property to.')

@section('actions')
    @can('create', App\Models\Buyer::class)
        <a href="{{ route('buyers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add buyer</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search by name or phone</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Search</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Phone</th><th>CNIC</th><th>Sales</th><th>Reservations</th><th></th></tr></thead>
                <tbody>
                @forelse ($buyers as $buyer)
                    <tr>
                        <td class="fw-semibold">{{ $buyer->name }}</td>
                        <td>{{ $buyer->phone ?: '-' }}</td>
                        <td>{{ $buyer->cnic ?: '-' }}</td>
                        <td>{{ $buyer->sales_count }}</td>
                        <td>{{ $buyer->reservations_count }}</td>
                        <td class="text-end">
                            @can('update', $buyer)
                                <a href="{{ route('buyers.edit', $buyer) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No buyers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $buyers->links() }}</div>
@endsection
