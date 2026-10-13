<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#eef1f5; }
        .auth-wrap { max-width: 480px; margin: 4rem auto; }
        .required::after { content:" *"; color:#b42318; }
    </style>
</head>
<body>
<div class="auth-wrap px-3">
    <div class="text-center mb-4">
        <h1 class="h4 fw-semibold mb-1">{{ config('app.name') }}</h1>
        <div class="text-muted small">@yield('tagline', 'Property purchases, sales, investors and accounts in one place.')</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">@yield('title')</h2>
            @include('partials.flash')
            @yield('content')
        </div>
    </div>

    <div class="text-center mt-3 small text-muted">@yield('footer')</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
