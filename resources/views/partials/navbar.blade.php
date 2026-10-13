@php
    $unread = auth()->user()?->unreadNotifications()->count() ?? 0;
@endphp
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="{{ auth()->user()?->isSuperAdmin() ? route('admin.dashboard') : (auth()->user()?->isInvestor() ? route('portal.dashboard') : route('dashboard')) }}">
            {{ config('app.name') }}
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topnav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                @auth
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="{{ route('notifications.index') }}">
                            <i class="bi bi-bell"></i> Notifications
                            @if ($unread > 0)
                                <span class="badge rounded-pill bg-danger">{{ $unread }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                            <span class="badge bg-secondary">{{ auth()->user()->roleLabel() }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if (auth()->user()->tenant)
                                <li><h6 class="dropdown-header">{{ auth()->user()->tenant->name }}</h6></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">My profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<div class="d-lg-none bg-white border-bottom p-2">
    <button class="btn btn-sm btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#mobilemenu">
        <i class="bi bi-list"></i> Menu
    </button>
    <div class="collapse mt-2" id="mobilemenu">
        @include('partials.sidebar')
    </div>
</div>
