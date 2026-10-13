<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Investor;
use App\Models\Sale;
use App\Models\User;
use App\Services\Investors\AllocationService;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen an account holder can reach has to actually render. A broken
 * view is just as much a defect as a broken calculation.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_workspace_screen_renders(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        // Give the workspace one of everything so the pages have content.
        $account = $this->makeCashAccount('Main cash', '5000000');
        $property = $this->makePurchasedProperty('1000000');
        $investor = $this->makeInvestor('Portfolio investor');
        $buyer = $this->makeBuyer('Test buyer');
        $this->makeSeller('Test seller');

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '600000'],
        ], now()->toDateString());

        $category = ExpenseCategory::where('slug', 'repairs-maintenance')->firstOrFail();
        Expense::create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '25000',
            'status' => Expense::STATUS_DRAFT,
        ]);

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        $expense = Expense::first();
        $purchase = $property->purchase;

        $urls = [
            '/dashboard',
            '/properties', '/properties/create', "/properties/{$property->id}",
            "/properties/{$property->id}/edit", "/properties/{$property->id}/ledger",
            "/properties/{$property->id}/allocations",
            '/investors', '/investors/create', "/investors/{$investor->id}",
            "/investors/{$investor->id}/edit", "/investors/{$investor->id}/statement",
            '/sellers', '/sellers/create',
            '/buyers', '/buyers/create',
            '/purchases', '/purchases/create', "/purchases/{$purchase->id}",
            '/expenses', '/expenses/create', "/expenses/{$expense->id}",
            '/accounts', '/accounts/create', "/accounts/{$account->id}/statement",
            '/transfers/create',
            '/reservations', '/reservations/create',
            '/sales', '/sales/create', "/sales/{$sale->id}", "/sales/{$sale->id}/correct",
            '/approvals',
            '/staff', '/staff/create',
            '/reports',
            '/settings', '/settings/audit-log',
            '/profile', '/notifications',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk("GET {$url} did not return 200");
        }
    }

    public function test_every_report_renders_and_exports(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        $this->makeCashAccount('Cash', '100000');
        $property = $this->makePurchasedProperty('800000');
        $buyer = $this->makeBuyer();

        app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '950000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        $reports = array_keys(app(\App\Services\Reports\ReportRegistry::class)->all());

        $this->assertCount(12, $reports, 'All twelve required reports should be registered.');

        foreach ($reports as $report) {
            $this->get("/reports/{$report}")->assertOk("Report {$report} did not render");
            $this->get("/reports/{$report}/export/csv")->assertOk("Report {$report} CSV export failed");
        }

        // One PDF is enough to prove the renderer works; they share a template.
        $this->get('/reports/realized-profit/export/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_super_admin_screens_render(): void
    {
        ['tenant' => $tenant] = $this->makeWorkspace();

        $admin = User::create([
            'tenant_id' => null,
            'name' => 'Platform admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin);

        $plan = \App\Models\Plan::first();

        foreach ([
            '/admin',
            '/admin/accounts',
            "/admin/accounts/{$tenant->id}",
            '/admin/plans',
            "/admin/plans/{$plan->id}/edit",
            '/admin/backups',
        ] as $url) {
            $this->get($url)->assertOk("GET {$url} did not return 200");
        }
    }

    public function test_the_investor_portal_renders(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Portal Co', [
            \App\Support\Entitlements::INVESTOR_PORTAL_ENABLED => true,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $investor = $this->makeInvestor('Portal investor');

        $portalUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $investor->name,
            'email' => 'portal-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_INVESTOR,
            'investor_id' => $investor->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $investor->update(['user_id' => $portalUser->id, 'portal_enabled' => true]);

        $this->actingInWorkspace($tenant, $portalUser);

        foreach (['/portal', '/portal/investments', '/portal/statement'] as $url) {
            $this->get($url)->assertOk("GET {$url} did not return 200");
        }
    }

    public function test_the_guest_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }
}
