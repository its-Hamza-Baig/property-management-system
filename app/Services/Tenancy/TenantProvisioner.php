<?php

namespace App\Services\Tenancy;

use App\Models\ExpenseCategory;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approvals\ApprovalManager;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything a brand new workspace needs before it can be used: its chart of
 * accounts, a starter set of expense categories, the approval rule rows, and
 * sensible notification defaults for the account holder.
 */
class TenantProvisioner
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ApprovalManager $approvals,
        private readonly TenantContext $context,
    ) {
    }

    /** Create the workspace and its account holder from a signup. */
    public function registerAccount(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'plan_id' => Plan::default()?->id,
                'status' => Tenant::STATUS_PENDING_VERIFICATION,
                'contact_email' => $data['email'],
                'contact_phone' => $data['phone'] ?? null,
            ]);

            User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_ACCOUNT_HOLDER,
                'is_active' => true,
                'phone' => $data['phone'] ?? null,
            ]);

            return $tenant;
        });
    }

    /**
     * Called when the Super Admin activates the account. Idempotent, so
     * re-activating a suspended workspace does not duplicate anything.
     */
    public function provision(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $this->ledger->seedChartOfAccounts($tenant->id);
            $this->approvals->ensureRules($tenant->id);
            $this->seedExpenseCategories($tenant);
            $this->seedNotificationPreferences($tenant);
        });
    }

    private function seedExpenseCategories(Tenant $tenant): void
    {
        $this->context->runFor($tenant, function () use ($tenant) {
            foreach (ExpenseCategory::defaults() as $definition) {
                $category = ExpenseCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'applies_to' => $definition['applies_to'],
                        'is_capitalized' => $definition['is_capitalized'],
                        'is_active' => true,
                    ],
                );

                // Each category gets its own ledger account so the expense
                // report by category is a straight ledger query.
                $this->ledger->createAccountForCategory($category);
            }
        });
    }

    private function seedNotificationPreferences(Tenant $tenant): void
    {
        foreach ($tenant->users as $user) {
            foreach (array_keys(NotificationPreference::TYPES) as $type) {
                NotificationPreference::firstOrCreate(
                    ['user_id' => $user->id, 'type' => $type],
                    ['in_app' => true, 'email' => false],
                );
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
