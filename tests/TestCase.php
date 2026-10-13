<?php

namespace Tests;

use App\Models\Investor;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Entitlements;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a fully provisioned workspace: chart of accounts, expense
     * categories, approval rules and an account holder.
     *
     * @return array{tenant: Tenant, owner: User}
     */
    protected function makeWorkspace(string $name = 'Acme Estates', array $entitlements = []): array
    {
        $plan = Plan::firstOrCreate(
            ['slug' => 'free'],
            ['name' => 'Free', 'is_default' => true, 'is_active' => true, 'limits' => Entitlements::defaults()],
        );

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'plan_id' => $plan->id,
            'status' => Tenant::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        foreach ($entitlements as $key => $value) {
            $tenant->entitlementOverrides()->create([
                'key' => $key,
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ]);
        }

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner of '.$name,
            'email' => 'owner-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_ACCOUNT_HOLDER,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        app(TenantProvisioner::class)->provision($tenant);

        return ['tenant' => $tenant->fresh(), 'owner' => $owner];
    }

    /** Put a workspace into context and authenticate as one of its users. */
    protected function actingInWorkspace(Tenant $tenant, User $user): static
    {
        app(TenantContext::class)->set($tenant);

        return $this->actingAs($user);
    }

    protected function makeStaff(Tenant $tenant, array $permissions = [], bool $active = true): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Staff member',
            'email' => 'staff-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_STAFF,
            'permissions' => $permissions,
            'is_active' => $active,
            'email_verified_at' => now(),
        ]);
    }

    protected function makeCashAccount(string $name = 'Main cash', string $opening = '0'): \App\Models\FinancialAccount
    {
        return app(CashService::class)->openAccount([
            'name' => $name,
            'type' => 'cash',
            'opening_balance' => $opening,
            'opening_date' => now()->toDateString(),
        ]);
    }

    protected function makeProperty(array $attributes = []): Property
    {
        return Property::create(array_merge([
            'reference' => 'P-'.strtoupper(substr(uniqid(), -6)),
            'title' => 'Test plot',
            'city' => 'Lahore',
            'property_type' => 'plot',
            'size' => 5,
            'size_unit' => 'marla',
            'status' => Property::STATUS_AVAILABLE,
        ], $attributes));
    }

    /** A property with its purchase already confirmed at the given price. */
    protected function makePurchasedProperty(string $price = '1000000', array $attributes = []): Property
    {
        $property = $this->makeProperty($attributes);

        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'purchase_date' => now()->toDateString(),
            'purchase_price' => $price,
            'status' => PropertyPurchase::STATUS_DRAFT,
        ]);

        app(\App\Services\Purchases\PurchaseService::class)->confirm($purchase);

        return $property->fresh();
    }

    protected function makeInvestor(string $name = 'Investor One'): Investor
    {
        return Investor::create([
            'name' => $name,
            'status' => Investor::STATUS_ACTIVE,
        ]);
    }

    protected function makeBuyer(string $name = 'Buyer One'): \App\Models\Buyer
    {
        return \App\Models\Buyer::create(['name' => $name]);
    }

    protected function makeSeller(string $name = 'Seller One'): \App\Models\Seller
    {
        return \App\Models\Seller::create(['name' => $name]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();

        parent::tearDown();
    }
}
