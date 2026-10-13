<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Public signup.
 *
 * The flow is: submit details, verify the email, then wait for the Super
 * Admin to activate the account. Nothing in the workspace is usable until
 * that approval happens.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly TenantProvisioner $provisioner)
    {
    }

    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $tenant = $this->provisioner->registerAccount($data);
        $user = $tenant->users()->first();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    /**
     * The holding page for an account that cannot be used yet: awaiting
     * approval, rejected, suspended or closed.
     */
    public function status(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if ($user->isSuperAdmin() || ($tenant && $tenant->status === Tenant::STATUS_ACTIVE)) {
            return redirect()->route($user->isSuperAdmin() ? 'admin.dashboard' : 'dashboard');
        }

        return view('auth.account-status', ['tenant' => $tenant]);
    }
}
