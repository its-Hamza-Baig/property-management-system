<?php

use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ResolveTenant;
use App\Services\Entitlements\EntitlementException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The workspace is resolved for every web request, before any
        // controller or policy runs.
        $middleware->web(append: [
            ResolveTenant::class,
        ]);

        $middleware->alias([
            'account.usable' => EnsureAccountIsUsable::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Hitting a plan limit is an ordinary situation, not a crash. Show the
        // account holder what happened instead of an error page.
        $exceptions->render(function (EntitlementException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage())->withInput();
        });
    })->create();
