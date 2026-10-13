<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Investor;
use App\Models\Property;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerQuery;
use App\Services\Sales\SaleService;
use App\Services\Tenancy\TenantContext;
use App\Support\Entitlements;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One account holder must never reach another account holder data, through
 * any route: pages, ids typed into the address bar, downloads, exports or
 * reports. Hiding a menu item is not isolation.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $acme;

    private array $globex;

    private Property $acmeProperty;

    private Investor $acmeInvestor;

    private Sale $acmeSale;

    private int $acmePurchaseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->makeWorkspace('Acme Estates');
        $this->globex = $this->makeWorkspace('Globex Property');

        // Build some real data inside Acme.
        $this->actingInWorkspace($this->acme['tenant'], $this->acme['owner']);
        $this->makeCashAccount('Acme cash', '2000000');
        $this->acmeProperty = $this->makePurchasedProperty('1000000', ['reference' => 'ACME-1']);
        $this->acmeInvestor = $this->makeInvestor('Acme investor');
        // Captured now: once we switch workspace the scope hides this row,
        // so the relation would resolve to null.
        $this->acmePurchaseId = $this->acmeProperty->purchase->id;
        $buyer = $this->makeBuyer('Acme buyer');

        $this->acmeSale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $this->acmeProperty->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1500000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        app(TenantContext::class)->clear();
    }

    public function test_another_account_holder_cannot_open_your_records(): void
    {
        $this->actingInWorkspace($this->globex['tenant'], $this->globex['owner']);

        // Guessing the id of somebody else record must not work.
        $this->get("/properties/{$this->acmeProperty->id}")->assertNotFound();
        $this->get("/properties/{$this->acmeProperty->id}/edit")->assertNotFound();
        $this->get("/properties/{$this->acmeProperty->id}/ledger")->assertNotFound();
        $this->get("/properties/{$this->acmeProperty->id}/allocations")->assertNotFound();
        $this->get("/investors/{$this->acmeInvestor->id}")->assertNotFound();
        $this->get("/investors/{$this->acmeInvestor->id}/statement")->assertNotFound();
        $this->get("/sales/{$this->acmeSale->id}")->assertNotFound();
        $this->get("/purchases/{$this->acmePurchaseId}")->assertNotFound();
    }

    public function test_another_account_holder_cannot_change_your_records(): void
    {
        $this->actingInWorkspace($this->globex['tenant'], $this->globex['owner']);

        $this->put("/properties/{$this->acmeProperty->id}", [
            'reference' => 'STOLEN', 'title' => 'Taken over', 'property_type' => 'plot', 'status' => 'available',
        ])->assertNotFound();

        $this->post("/sales/{$this->acmeSale->id}/finalize")->assertNotFound();
        $this->post("/sales/{$this->acmeSale->id}/cancel", [
            'cancellation_reason' => 'Not mine to cancel',
            'cancellation_date' => now()->toDateString(),
        ])->assertNotFound();

        $this->assertSame('ACME-1', $this->acmeProperty->fresh()->reference);
        $this->assertSame(Sale::STATUS_FINALIZED, $this->acmeSale->fresh()->status);
    }

    public function test_lists_and_reports_only_ever_show_your_own_workspace(): void
    {
        $this->actingInWorkspace($this->globex['tenant'], $this->globex['owner']);

        $this->get('/properties')->assertOk()->assertDontSee('ACME-1');
        $this->get('/investors')->assertOk()->assertDontSee('Acme investor');
        $this->get('/sales')->assertOk()->assertDontSee('Acme buyer');
        $this->get('/reports/property-status')->assertOk()->assertDontSee('ACME-1');
        $this->get('/reports/realized-profit')->assertOk()->assertDontSee('1,500,000');
    }

    public function test_exports_are_scoped_to_your_own_workspace(): void
    {
        $this->actingInWorkspace($this->globex['tenant'], $this->globex['owner']);

        $response = $this->get('/reports/property-status/export/csv')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringNotContainsString('ACME-1', $csv);
    }

    public function test_the_ledger_totals_of_one_workspace_never_leak_into_another(): void
    {
        $ledger = app(LedgerQuery::class);

        app(TenantContext::class)->set($this->acme['tenant']);
        $this->assertSame('1500000.00', $ledger->totalIncome()->toDecimal());

        app(TenantContext::class)->set($this->globex['tenant']);
        $this->assertSame('0.00', $ledger->totalIncome()->toDecimal());
        $this->assertSame('0.00', $ledger->totalCash()->toDecimal());
    }

    public function test_a_document_belonging_to_another_workspace_cannot_be_downloaded(): void
    {
        $this->actingInWorkspace($this->acme['tenant'], $this->acme['owner']);

        $document = Document::create([
            'documentable_type' => $this->acmeProperty->getMorphClass(),
            'documentable_id' => $this->acmeProperty->id,
            'category' => 'purchase_agreement',
            'title' => 'Acme agreement',
            'uploaded_by' => $this->acme['owner']->id,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'disk' => 'private',
            'path' => 'tenant-1/fake.pdf',
            'original_name' => 'agreement.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);

        $this->actingInWorkspace($this->globex['tenant'], $this->globex['owner']);

        $this->get("/documents/{$document->id}/download")->assertNotFound();
    }

    public function test_staff_are_confined_to_their_own_workspace_and_permissions(): void
    {
        $staff = $this->makeStaff($this->globex['tenant'], [Permissions::PROPERTIES_VIEW]);
        $this->actingInWorkspace($this->globex['tenant'], $staff);

        // Their own workspace, with the permission they hold.
        $this->get('/properties')->assertOk();

        // Another workspace record stays invisible.
        $this->get("/properties/{$this->acmeProperty->id}")->assertNotFound();

        // A permission they do not hold is refused even in their own workspace.
        $this->get('/investors')->assertForbidden();
        $this->get('/staff')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/properties/create')->assertForbidden();
    }

    public function test_a_deactivated_staff_member_cannot_sign_in_or_keep_a_session(): void
    {
        $staff = $this->makeStaff($this->globex['tenant'], [Permissions::PROPERTIES_VIEW]);
        $staff->update(['is_active' => false]);

        // setUp signed somebody in; the login form is for guests.
        auth()->logout();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Even an already open session is dropped on the next request.
        $this->actingInWorkspace($this->globex['tenant'], $staff);
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_investor_portal_user_sees_only_their_own_information(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Portal Co', [
            Entitlements::INVESTOR_PORTAL_ENABLED => true,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $mine = $this->makeInvestor('My investor');
        $other = $this->makeInvestor('Another investor');

        $portalUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $mine->name,
            'email' => 'portal-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_INVESTOR,
            'investor_id' => $mine->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $mine->update(['user_id' => $portalUser->id, 'portal_enabled' => true]);

        $this->actingInWorkspace($tenant, $portalUser);

        $this->get('/portal')->assertOk()->assertSee('My investor')->assertDontSee('Another investor');

        // The whole back office is closed to them.
        $this->get('/dashboard')->assertForbidden();
        $this->get('/properties')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get("/investors/{$other->id}")->assertForbidden();
    }

    public function test_portal_access_stops_working_when_the_account_holder_switches_it_off(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Portal Off Co', [
            Entitlements::INVESTOR_PORTAL_ENABLED => true,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $investor = $this->makeInvestor('Switched off');
        $portalUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => $investor->name,
            'email' => 'off-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_INVESTOR,
            'investor_id' => $investor->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $investor->update(['user_id' => $portalUser->id, 'portal_enabled' => false]);

        $this->actingInWorkspace($tenant, $portalUser);
        $this->get('/portal')->assertForbidden();
    }

    public function test_a_super_admin_has_no_standing_access_to_customer_data(): void
    {
        $admin = User::create([
            'tenant_id' => null,
            'name' => 'Platform admin',
            'email' => 'sa-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin);

        // The whole workspace area is closed to the platform role.
        $this->get('/dashboard')->assertForbidden();
        $this->get('/properties')->assertForbidden();
        $this->get('/reports/realized-profit')->assertForbidden();

        // A named record does not even resolve, because the query scope finds
        // nothing without a workspace in context. A 404 leaks less than a 403.
        $this->get("/properties/{$this->acmeProperty->id}")->assertNotFound();

        // And the admin screens show no financial figures.
        $this->get("/admin/accounts/{$this->acme['tenant']->id}")
            ->assertOk()
            ->assertDontSee('1,500,000');
    }

    public function test_support_access_is_recorded_in_the_customer_audit_log(): void
    {
        $admin = User::create([
            'tenant_id' => null,
            'name' => 'Support admin',
            'email' => 'support-'.uniqid().'@example.com',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $tenant = $this->acme['tenant'];

        $this->actingAs($admin)
            ->post("/admin/support-access/{$tenant->id}", [
                'reason' => 'Ticket 1042: investor statement total looks wrong',
                'minutes' => 30,
            ])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('support_access_grants', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'support_access.granted',
        ]);

        // With the grant live the workspace opens, and the visit is logged.
        $this->get('/dashboard')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'support_access.request',
        ]);
    }

    public function test_a_suspended_account_is_blocked_but_loses_nothing(): void
    {
        $tenant = $this->acme['tenant'];
        $tenant->update(['status' => Tenant::STATUS_SUSPENDED, 'status_reason' => 'Payment overdue']);

        $this->actingInWorkspace($tenant->fresh(), $this->acme['owner']);
        $this->get('/dashboard')->assertRedirect('/account-status');

        // Every record is still exactly where it was.
        $this->assertDatabaseHas('properties', ['id' => $this->acmeProperty->id, 'reference' => 'ACME-1']);
        $this->assertDatabaseHas('sales', ['id' => $this->acmeSale->id, 'status' => Sale::STATUS_FINALIZED]);
        $this->assertDatabaseHas('journals', ['tenant_id' => $tenant->id]);
    }
}
