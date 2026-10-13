<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f5f6f8; }
        .sidebar { min-height: calc(100vh - 56px); background:#fff; border-right:1px solid #dee2e6; }
        .sidebar .nav-link { color:#344054; border-radius:.375rem; padding:.45rem .75rem; margin-bottom:.1rem; }
        .sidebar .nav-link:hover { background:#eef1f5; }
        .sidebar .nav-link.active { background:#0d6efd; color:#fff; }
        .sidebar .nav-heading { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em;
            color:#8a94a6; margin:1rem .75rem .35rem; font-weight:600; }
        .kpi-card .kpi-value { font-size:1.4rem; font-weight:600; }
        .kpi-card .kpi-label { font-size:.8rem; color:#6c757d; }
        .table-sm td, .table-sm th { padding:.45rem .5rem; }
        .money { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .text-money-negative { color:#b42318; }
        .required::after { content:" *"; color:#b42318; }
        .empty-state { padding:2.5rem 1rem; text-align:center; color:#6c757d; }
    </style>
    @stack('head')
</head>
<body>
@include('partials.navbar')

<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-2 d-none d-lg-block sidebar py-3">
            @include('partials.sidebar')
        </nav>

        <main class="col-lg-10 px-lg-4 py-3">
            @if (app(\App\Services\Tenancy\TenantContext::class)->isSupportAccess())
                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Support access.</strong>
                        You are viewing a customer workspace under a recorded support grant. Every page you open is written to their audit log.
                    </div>
                    <form method="POST" action="{{ route('admin.support.destroy') }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-dark">Leave workspace</button>
                    </form>
                </div>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h1 class="h4 mb-0">@yield('heading', View::getSection('title', 'Dashboard'))</h1>
                    @hasSection('subheading')
                        <div class="text-muted small">@yield('subheading')</div>
                    @endif
                </div>
                <div>@yield('actions')</div>
            </div>

            @include('partials.flash')

            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
