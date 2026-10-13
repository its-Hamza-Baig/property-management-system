<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Deliberately vague: do not reveal whether the address exists.
            throw ValidationException::withMessages([
                'email' => 'Those details do not match our records.',
            ]);
        }

        $user = Auth::user();

        // A deactivated staff member keeps their row and their history but
        // loses access the moment they are switched off.
        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your account administrator.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log(
            action: 'auth.login',
            subject: $user,
            description: 'Signed in',
            tenantId: $user->tenant_id,
        );

        return redirect()->intended($this->homeFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->audit->log(
            action: 'auth.logout',
            subject: $user,
            description: 'Signed out',
            tenantId: $user?->tenant_id,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function homeFor(User $user): string
    {
        return match (true) {
            $user->isSuperAdmin() => route('admin.dashboard'),
            $user->isInvestor() => route('portal.dashboard'),
            default => route('dashboard'),
        };
    }
}
