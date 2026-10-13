@extends('layouts.app')
@section('title', 'Notifications')

@section('actions')
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn btn-sm btn-outline-secondary">Mark all as read</button>
    </form>
@endsection

@section('content')
    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <li class="list-group-item {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="fw-semibold">
                                {{ $data['title'] ?? 'Notification' }}
                                @unless ($notification->read_at)<span class="badge bg-primary">New</span>@endunless
                            </div>
                            <div class="small">{{ $data['message'] ?? '' }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="text-nowrap">
                            @if (! empty($data['url']))
                                <a href="{{ $data['url'] }}" class="btn btn-sm btn-outline-primary">Open</a>
                            @endif
                            @unless ($notification->read_at)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Mark read</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </li>
            @empty
                <li class="list-group-item empty-state">You have no notifications.</li>
            @endforelse
        </ul>
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
