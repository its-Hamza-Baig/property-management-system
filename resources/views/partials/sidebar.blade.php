@php
    use App\Support\Permissions;
    $user = auth()->user();
@endphp

<ul class="nav flex-column">
    @if ($user?->isSuperAdmin())
        <li><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Platform</a></li>
        <li><a class="nav-link {{ request()->routeIs('admin.accounts.*') ? 'active' : '' }}" href="{{ route('admin.accounts.index') }}"><i class="bi bi-building"></i> Customer accounts</a></li>
        <li><a class="nav-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}"><i class="bi bi-sliders"></i> Plans and limits</a></li>
        <li><a class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" href="{{ route('admin.backups.index') }}"><i class="bi bi-hdd"></i> Backups</a></li>

    @elseif ($user?->isInvestor())
        <li><a class="nav-link {{ request()->routeIs('portal.dashboard') ? 'active' : '' }}" href="{{ route('portal.dashboard') }}"><i class="bi bi-speedometer2"></i> Overview</a></li>
        <li><a class="nav-link {{ request()->routeIs('portal.investments') ? 'active' : '' }}" href="{{ route('portal.investments') }}"><i class="bi bi-pie-chart"></i> My investments</a></li>
        <li><a class="nav-link {{ request()->routeIs('portal.statement') ? 'active' : '' }}" href="{{ route('portal.statement') }}"><i class="bi bi-receipt"></i> My statement</a></li>

    @elseif ($user)
        <li><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a></li>

        <div class="nav-heading">Portfolio</div>
        @can(Permissions::PROPERTIES_VIEW)
            <li><a class="nav-link {{ request()->routeIs('properties.*') ? 'active' : '' }}" href="{{ route('properties.index') }}"><i class="bi bi-houses"></i> Properties</a></li>
        @endcan
        @can(Permissions::INVESTORS_VIEW)
            <li><a class="nav-link {{ request()->routeIs('investors.*') ? 'active' : '' }}" href="{{ route('investors.index') }}"><i class="bi bi-people"></i> Investors</a></li>
        @endcan

        <div class="nav-heading">Buying</div>
        @can(Permissions::PURCHASES_VIEW)
            <li><a class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.index') }}"><i class="bi bi-cart-check"></i> Purchases</a></li>
            <li><a class="nav-link {{ request()->routeIs('sellers.*') ? 'active' : '' }}" href="{{ route('sellers.index') }}"><i class="bi bi-person-badge"></i> Sellers</a></li>
        @endcan
        @can(Permissions::EXPENSES_VIEW)
            <li><a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}"><i class="bi bi-receipt-cutoff"></i> Expenses</a></li>
        @endcan

        <div class="nav-heading">Selling</div>
        @can(Permissions::SALES_VIEW)
            <li><a class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}"><i class="bi bi-bookmark-star"></i> Reservations</a></li>
            <li><a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}"><i class="bi bi-cash-coin"></i> Sales</a></li>
            <li><a class="nav-link {{ request()->routeIs('buyers.*') ? 'active' : '' }}" href="{{ route('buyers.index') }}"><i class="bi bi-person-lines-fill"></i> Buyers</a></li>
        @endcan

        <div class="nav-heading">Money</div>
        @can(Permissions::ACCOUNTS_VIEW)
            <li><a class="nav-link {{ request()->routeIs('accounts.*') || request()->routeIs('transfers.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}"><i class="bi bi-bank"></i> Cash and bank</a></li>
        @endcan
        @can(Permissions::REPORTS_VIEW)
            <li><a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
        @endcan

        <div class="nav-heading">Administration</div>
        <li>
            <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">
                <i class="bi bi-check2-square"></i> Approvals
                @php $pending = \App\Models\ApprovalRequest::pending()->count(); @endphp
                @if ($pending > 0)<span class="badge bg-warning text-dark">{{ $pending }}</span>@endif
            </a>
        </li>
        @can(Permissions::STAFF_MANAGE)
            <li><a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}" href="{{ route('staff.index') }}"><i class="bi bi-person-gear"></i> Staff</a></li>
        @endcan
        @can(Permissions::SETTINGS_MANAGE)
            <li><a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i class="bi bi-gear"></i> Settings</a></li>
        @endcan
    @endif
</ul>
