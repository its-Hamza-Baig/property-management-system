<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Support\Entitlements;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementManager $entitlements,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('staff.index', [
            'staff' => User::where('role', User::ROLE_STAFF)->orderBy('name')->paginate(20),
            'used' => $this->entitlements->staffUsed(),
            'limit' => $this->entitlements->value(Entitlements::MAX_STAFF),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);
        $this->entitlements->enforceCanAddStaff();

        return view('staff.form', [
            'staff' => new User(['role' => User::ROLE_STAFF, 'is_active' => true]),
            'groups' => Permissions::groups(),
            'selected' => Permissions::staffPreset(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $this->entitlements->enforceCanAddStaff();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'preset' => ['nullable', Rule::in(['admin', 'staff', 'custom'])],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        $staff = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => User::ROLE_STAFF,
            'permissions' => $this->resolvePermissions($data),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (array_keys(NotificationPreference::TYPES) as $type) {
            NotificationPreference::firstOrCreate(
                ['user_id' => $staff->id, 'type' => $type],
                ['in_app' => true, 'email' => false],
            );
        }

        $this->audit->log('staff.created', $staff, $staff->name);

        return redirect()->route('staff.index')->with('status', 'Staff member added.');
    }

    public function show(User $staff): RedirectResponse
    {
        return redirect()->route('staff.edit', $staff);
    }

    public function edit(User $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.form', [
            'staff' => $staff,
            'groups' => Permissions::groups(),
            'selected' => $staff->permissions ?? [],
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
            'preset' => ['nullable', Rule::in(['admin', 'staff', 'custom'])],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        $before = ['permissions' => json_encode($staff->permissions), 'email' => $staff->email];

        $staff->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'permissions' => $this->resolvePermissions($data),
        ]);

        if (filled($data['password'] ?? null)) {
            $staff->password = $data['password'];
        }

        $staff->save();

        $this->audit->logChange('staff.updated', $staff, $before);

        return redirect()->route('staff.index')->with('status', 'Staff member updated.');
    }

    /**
     * Switching a staff member off revokes their access straight away and
     * keeps every record they created.
     */
    public function setStatus(Request $request, User $staff): RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        $activating = $request->boolean('is_active');

        if ($activating) {
            $this->authorize('update', $staff);
            $this->entitlements->enforceCanAddStaff();
        } else {
            $this->authorize('deactivate', $staff);
        }

        $staff->update([
            'is_active' => $activating,
            'deactivated_at' => $activating ? null : now(),
        ]);

        $this->audit->log('staff.status_changed', $staff, $activating ? 'Activated' : 'Deactivated');

        return back()->with('status', $activating
            ? 'Staff member reactivated.'
            : 'Staff member deactivated. They can no longer sign in, and their past work has been kept.');
    }

    /** @param array{preset?:string,permissions?:array} $data */
    private function resolvePermissions(array $data): array
    {
        return match ($data['preset'] ?? 'custom') {
            'admin' => Permissions::adminPreset(),
            'staff' => Permissions::staffPreset(),
            default => array_values($data['permissions'] ?? []),
        };
    }
}
