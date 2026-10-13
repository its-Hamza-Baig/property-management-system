<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Approvals\ApprovalManager;
use App\Support\Entitlements;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EntitlementsAndApprovalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_active_property_limit_is_enforced_and_sold_stock_does_not_count(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Limited Co', [
            Entitlements::MAX_PROPERTIES => 2,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $this->makeProperty(['reference' => 'A-1']);
        $this->makeProperty(['reference' => 'A-2']);

        // At the limit, a third is refused with an explanation.
        $this->post('/properties', $this->propertyPayload('A-3'))
            ->assertRedirect();
        $this->assertDatabaseMissing('properties', ['reference' => 'A-3']);

        // Selling one frees a slot, because sold stock is not active inventory.
        Property::where('reference', 'A-1')->update(['status' => Property::STATUS_SOLD]);

        $this->post('/properties', $this->propertyPayload('A-3'));
        $this->assertDatabaseHas('properties', ['reference' => 'A-3']);
    }

    public function test_the_staff_limit_is_enforced(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Staffed Co', [
            Entitlements::MAX_STAFF => 1,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $this->makeStaff($tenant, [Permissions::PROPERTIES_VIEW]);

        $this->get('/staff/create')->assertRedirect();

        $this->post('/staff', [
            'name' => 'Second staff',
            'email' => 'second@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'preset' => 'staff',
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_export_is_refused_when_the_plan_does_not_include_it(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('No Export Co', [
            Entitlements::EXPORTS_ENABLED => false,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        // The screen still works; only the export is blocked.
        $this->get('/reports/property-status')->assertOk();
        $this->get('/reports/property-status/export/csv')->assertRedirect();
    }

    public function test_document_upload_is_refused_without_the_entitlement(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('No Docs Co', [
            Entitlements::DOCUMENTS_ENABLED => false,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $property = $this->makeProperty();

        $this->post('/documents', [
            'owner_type' => 'property',
            'owner_id' => $property->id,
            'category' => 'other',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('deed.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_losing_an_entitlement_does_not_delete_anything(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace('Downgrade Co', [
            Entitlements::MAX_PROPERTIES => 5,
        ]);
        $this->actingInWorkspace($tenant, $owner);

        $this->makeProperty(['reference' => 'D-1']);
        $this->makeProperty(['reference' => 'D-2']);
        $this->makeProperty(['reference' => 'D-3']);

        // Downgrade well below what they already hold.
        $tenant->entitlementOverrides()->updateOrCreate(
            ['key' => Entitlements::MAX_PROPERTIES],
            ['value' => '1'],
        );
        app(\App\Services\Entitlements\EntitlementManager::class)->forget();

        // Everything they had is untouched and still visible.
        $this->assertSame(3, Property::count());
        $this->get('/properties')->assertOk()->assertSee('D-1')->assertSee('D-3');

        // Only the next addition is refused.
        $this->post('/properties', $this->propertyPayload('D-4'));
        $this->assertDatabaseMissing('properties', ['reference' => 'D-4']);
    }

    public function test_an_operation_that_needs_approval_cannot_be_completed_first(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        ApprovalRule::where('action', ApprovalRule::ACTION_PROPERTY_PURCHASE)
            ->update(['is_enabled' => true, 'threshold_amount' => '500000']);

        $property = $this->makeProperty();
        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'purchase_date' => now()->toDateString(),
            'purchase_price' => '900000',
            'status' => PropertyPurchase::STATUS_DRAFT,
        ]);

        $this->post("/purchases/{$purchase->id}/confirm");

        // Not confirmed, a request was raised, and nothing was posted.
        $this->assertSame(PropertyPurchase::STATUS_PENDING_APPROVAL, $purchase->fresh()->status);
        $this->assertDatabaseHas('approval_requests', [
            'action' => ApprovalRule::ACTION_PROPERTY_PURCHASE,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_an_amount_below_the_threshold_goes_through_without_approval(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        ApprovalRule::where('action', ApprovalRule::ACTION_PROPERTY_PURCHASE)
            ->update(['is_enabled' => true, 'threshold_amount' => '500000']);

        $property = $this->makeProperty();
        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'purchase_date' => now()->toDateString(),
            'purchase_price' => '200000',
            'status' => PropertyPurchase::STATUS_DRAFT,
        ]);

        $this->post("/purchases/{$purchase->id}/confirm");

        $this->assertSame(PropertyPurchase::STATUS_CONFIRMED, $purchase->fresh()->status);
    }

    public function test_nobody_can_approve_their_own_request(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        ApprovalRule::where('action', ApprovalRule::ACTION_EXPENSE)
            ->update(['is_enabled' => true, 'threshold_amount' => null]);

        $property = $this->makePurchasedProperty('500000');
        $category = ExpenseCategory::where('slug', 'repairs-maintenance')->firstOrFail();

        $expense = Expense::create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '10000',
            'status' => Expense::STATUS_DRAFT,
        ]);

        $this->post("/expenses/{$expense->id}/confirm");

        $request = ApprovalRequest::firstOrFail();

        // The requester is the account holder, who raised it.
        $this->post("/approvals/{$request->id}/approve")->assertForbidden();
        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->fresh()->status);

        // Calling the service directly is refused too, not just the route.
        $this->expectException(RuntimeException::class);
        app(ApprovalManager::class)->approve($request, $owner);
    }

    public function test_a_colleague_can_approve_and_then_the_operation_completes(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        ApprovalRule::where('action', ApprovalRule::ACTION_SALE_FINALIZATION)
            ->update(['is_enabled' => true, 'threshold_amount' => null]);

        $property = $this->makePurchasedProperty('1000000');
        $buyer = $this->makeBuyer();

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1300000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        $this->post("/sales/{$sale->id}/finalize");
        $this->assertSame(Sale::STATUS_PENDING_APPROVAL, $sale->fresh()->status);

        $approver = $this->makeStaff($tenant, [
            Permissions::APPROVALS_DECIDE,
            Permissions::SALES_VIEW,
            Permissions::SALES_FINALIZE,
        ]);

        $request = ApprovalRequest::firstOrFail();

        $this->actingInWorkspace($tenant, $approver);
        $this->post("/approvals/{$request->id}/approve", ['reason' => 'Checked against the agreement']);

        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $request->fresh()->status);

        // Now it goes through.
        $this->post("/sales/{$sale->id}/finalize");
        $sale->refresh();

        $this->assertSame(Sale::STATUS_FINALIZED, $sale->status);
        $this->assertSame('300000.00', $sale->realized_profit);
    }

    public function test_a_staff_member_without_the_permission_cannot_decide(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);

        ApprovalRule::where('action', ApprovalRule::ACTION_EXPENSE)
            ->update(['is_enabled' => true, 'threshold_amount' => null]);

        $property = $this->makePurchasedProperty('500000');
        $category = ExpenseCategory::where('slug', 'repairs-maintenance')->firstOrFail();
        $expense = Expense::create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '10000',
            'status' => Expense::STATUS_DRAFT,
        ]);

        $this->post("/expenses/{$expense->id}/confirm");
        $request = ApprovalRequest::firstOrFail();

        $plainStaff = $this->makeStaff($tenant, [Permissions::EXPENSES_VIEW]);
        $this->actingInWorkspace($tenant, $plainStaff);

        $this->post("/approvals/{$request->id}/approve")->assertForbidden();
        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_a_new_account_is_not_usable_until_the_platform_activates_it(): void
    {
        $this->post('/register', [
            'name' => 'Hopeful Owner',
            'company_name' => 'Hopeful Estates',
            'email' => 'hopeful@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect('/email/verify');

        $tenant = Tenant::where('contact_email', 'hopeful@example.com')->firstOrFail();
        $this->assertSame(Tenant::STATUS_PENDING_VERIFICATION, $tenant->status);

        $user = $tenant->users()->firstOrFail();

        // Verifying the email moves it into the queue, it does not activate it.
        $user->markEmailAsVerified();
        $tenant->update(['status' => Tenant::STATUS_PENDING_APPROVAL]);

        $this->actingAs($user->fresh());
        $this->get('/dashboard')->assertRedirect('/account-status');
        $this->get('/account-status')->assertOk()->assertSee('Awaiting approval');
    }

    private function propertyPayload(string $reference): array
    {
        return [
            'reference' => $reference,
            'title' => 'Test property',
            'property_type' => 'plot',
            'status' => Property::STATUS_AVAILABLE,
        ];
    }
}
