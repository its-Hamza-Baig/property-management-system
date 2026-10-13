<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.status');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'This verification link is not valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));

            // Verifying the address is what moves the workspace into the
            // Super Admin queue. It does not activate it.
            $tenant = $user->tenant;

            if ($tenant && $tenant->status === Tenant::STATUS_PENDING_VERIFICATION) {
                $tenant->update(['status' => Tenant::STATUS_PENDING_APPROVAL]);
            }
        }

        return redirect()->route('account.status')
            ->with('status', 'Thank you, your email address is confirmed.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.status');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'We have sent you another verification email.');
    }
}
