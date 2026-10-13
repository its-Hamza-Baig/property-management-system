@extends('layouts.app')
@section('title', 'Staff')
@section('subheading', $used.' active'.((int) $limit === -1 ? '' : ' of '.$limit.' allowed').'. Deactivating revokes access immediately and keeps all their past work.')

@section('actions')
    @can('create', App\Models\User::class)
        <a href="{{ route('staff.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add staff member</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Email</th><th>Permissions</th><th>Last signed in</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td class="fw-semibold">{{ $member->name }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ count($member->permissions ?? []) }} granted</td>
                        <td>{{ $member->last_login_at ? pms_date($member->last_login_at, 'd M Y H:i') : 'Never' }}</td>
                        <td>
                            <span class="badge {{ $member->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $member->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </td>
                        <td class="text-end">
                            @can('update', $member)
                                <a href="{{ route('staff.edit', $member) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endcan
                            <form method="POST" action="{{ route('staff.status', $member) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">
                                <button class="btn btn-sm {{ $member->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $member->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No staff members yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $staff->links() }}</div>
@endsection
