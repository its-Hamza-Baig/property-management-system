<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function edit(Request $request): View
    {
        $user = $request->user();

        foreach (array_keys(NotificationPreference::TYPES) as $type) {
            NotificationPreference::firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['in_app' => true, 'email' => false],
            );
        }

        return view('profile.edit', [
            'user' => $user,
            'preferences' => NotificationPreference::where('user_id', $user->id)->get()->keyBy('type'),
            'types' => NotificationPreference::TYPES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        // Changing the address means confirming it again.
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        $this->audit->log('auth.password_changed', $request->user(), 'Password changed');

        return back()->with('status', 'Password changed.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['array'],
            'preferences.*.in_app' => ['nullable', 'boolean'],
            'preferences.*.email' => ['nullable', 'boolean'],
        ]);

        foreach ($data['preferences'] ?? [] as $type => $values) {
            if (! array_key_exists($type, NotificationPreference::TYPES)) {
                continue;
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $type],
                [
                    'in_app' => (bool) ($values['in_app'] ?? false),
                    'email' => (bool) ($values['email'] ?? false),
                ],
            );
        }

        return back()->with('status', 'Notification preferences saved.');
    }
}
