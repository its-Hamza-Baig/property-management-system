<?php

namespace Database\Seeders;

use App\Models\ApprovalRule;
use App\Models\Buyer;
use App\Models\BuyerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Investor;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\PropertyPurchaseSeller;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Expenses\ExpenseService;
use App\Services\Investors\AllocationService;
use App\Services\Investors\DistributionService;
use App\Services\Ledger\LedgerQuery;
use App\Services\Purchases\PurchaseService;
use App\Services\Sales\BuyerPaymentService;
use App\Services\Sales\SaleService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * A fully populated demo workspace.
 *
 * Everything here goes through the real services, so the ledger it produces is
 * the same one the application would produce by hand. That makes it useful for
 * looking at the reports, and it doubles as an end-to-end exercise of the
 * purchase, sale, investor and approval flows.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private Tenant $tenant;

    private User $owner;

    private User $manager;

    private array $accounts = [];

    private array $investors = [];

    private array $buyers = [];

    private array $sellers = [];

    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $this->tenant = $this->workspace();

        app(TenantContext::class)->set($this->tenant);
        Auth::login($this->owner);

        $this->command?->info('Workspace: '.$this->tenant->name);

        $this->openAccounts();
        $this->createPeople();
        $this->takeInvestorCapital();

        $this->availablePlotWithInvestor();
        $this->plotUnderSaleWithInstallments();
        $this->soldWithInvestorProfitPartlyPaid();
        $this->soldOutrightNoInvestors();
        $this->reservedWithToken();
        $this->purchaseStillOwingTheSeller();
        $this->cancelledSaleThenResold();
        $this->purchaseWaitingForApproval();

        $this->report();

        Auth::logout();
        app(TenantContext::class)->clear();
    }

    private function workspace(): Tenant
    {
        $tenant = Tenant::firstWhere('slug', 'shaheen-estates');

        if ($tenant) {
            $this->command?->warn('The demo workspace already exists. Delete it first to rebuild:');
            $this->command?->warn('  php artisan tinker --execute="App\Models\Tenant::where(\'slug\',\'shaheen-estates\')->forceDelete();"');

            $this->owner = User::where('tenant_id', $tenant->id)
                ->where('role', User::ROLE_ACCOUNT_HOLDER)->firstOrFail();
            $this->manager = User::where('tenant_id', $tenant->id)
                ->where('role', User::ROLE_STAFF)->firstOrFail();

            return $tenant;
        }

        // The Pilot plan so documents and the investor portal are available.
        $plan = Plan::firstWhere('slug', 'pilot') ?? Plan::default();

        $tenant = Tenant::create([
            'name' => 'Shaheen Estates',
            'slug' => 'shaheen-estates',
            'plan_id' => $plan?->id,
            'status' => Tenant::STATUS_ACTIVE,
            'contact_email' => 'owner@demo.test',
            'contact_phone' => '0300 1234567',
            'activated_at' => now(),
        ]);

        $this->owner = $this->user($tenant, 'Hamza Shaheen', 'owner@demo.test', User::ROLE_ACCOUNT_HOLDER);

        app(TenantProvisioner::class)->provision($tenant);

        // An office manager who can do almost everything, and a junior who can
        // only record day to day entries. Both are needed to show approvals,
        // because nobody may approve their own request.
        $this->manager = $this->user($tenant, 'Ayesha Khan', 'manager@demo.test', User::ROLE_STAFF, Permissions::adminPreset());
        $this->user($tenant, 'Bilal Ahmed', 'staff@demo.test', User::ROLE_STAFF, Permissions::staffPreset());

        // Approvals on, so one purchase can sit waiting in the demo.
        ApprovalRule::where('tenant_id', $tenant->id)
            ->where('action', ApprovalRule::ACTION_PROPERTY_PURCHASE)
            ->update(['is_enabled' => true, 'threshold_amount' => '5000000']);

        return $tenant;
    }

    private function user(Tenant $tenant, string $name, string $email, string $role, array $permissions = []): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'permissions' => $permissions ?: null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        foreach (array_keys(NotificationPreference::TYPES) as $type) {
            NotificationPreference::firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['in_app' => true, 'email' => false],
            );
        }

        return $user;
    }

    private function openAccounts(): void
    {
        $cash = app(CashService::class);

        $this->accounts['cash'] = $cash->openAccount([
            'name' => 'Cash in hand',
            'type' => 'cash',
            'opening_balance' => '2500000',
            'opening_date' => now()->subMonths(14)->toDateString(),
        ]);

        $this->accounts['bank'] = $cash->openAccount([
            'name' => 'Business current account',
            'type' => 'bank',
            'bank_name' => 'Meezan Bank',
            'account_number' => '0102 0304 0506',
            'opening_balance' => '30000000',
            'opening_date' => now()->subMonths(14)->toDateString(),
        ]);

        // A transfer, so the demo shows one that is correctly not income.
        $cash->transfer([
            'from_financial_account_id' => $this->accounts['bank']->id,
            'to_financial_account_id' => $this->accounts['cash']->id,
            'transfer_date' => now()->subMonths(13)->toDateString(),
            'amount' => '1500000',
            'reference' => 'Cash withdrawal for site expenses',
        ]);
    }

    private function createPeople(): void
    {
        foreach ([
            'imran' => ['Imran Qureshi', '0301 2223344', '35202-1234567-1'],
            'sana' => ['Sana Malik', '0321 5556677', '35202-7654321-2'],
            'bilal' => ['Bilal Sheikh', '0333 8889900', '35202-1122334-3'],
        ] as $key => [$name, $phone, $cnic]) {
            $this->investors[$key] = Investor::create([
                'name' => $name,
                'phone' => $phone,
                'cnic' => $cnic,
                'email' => strtolower(explode(' ', $name)[0]).'@demo.test',
                'status' => Investor::STATUS_ACTIVE,
                'created_by' => $this->owner->id,
            ]);
        }

        // One investor gets a portal login, to show the read-only view.
        // The link has to be made in both directions: the investor points at
        // the login, and the login points back at the investor record it is
        // allowed to see.
        $portalUser = $this->user($this->tenant, $this->investors['imran']->name, 'investor@demo.test', User::ROLE_INVESTOR);
        $portalUser->update(['investor_id' => $this->investors['imran']->id]);

        $this->investors['imran']->update([
            'user_id' => $portalUser->id,
            'portal_enabled' => true,
            'email' => 'investor@demo.test',
        ]);

        foreach (['Rashid Property Dealers', 'Hajji Akram', 'Nasir Builders'] as $index => $name) {
            $this->sellers[$index] = Seller::create(['name' => $name, 'phone' => '042 3570'.$index.'00']);
        }

        foreach ([
            'Dr Faisal Mehmood', 'Mrs Shazia Tariq', 'Kamran Enterprises', 'Usman Ghani',
        ] as $index => $name) {
            $this->buyers[$index] = Buyer::create(['name' => $name, 'phone' => '0300 99988'.$index.'0']);
        }
    }

    private function takeInvestorCapital(): void
    {
        $allocations = app(AllocationService::class);

        foreach ([
            'imran' => '4500000',
            'sana' => '4200000',
            'bilal' => '2700000',
        ] as $key => $amount) {
            $allocations->recordCapitalReceipt($this->investors[$key], [
                'amount' => $amount,
                'financial_account_id' => $this->accounts['bank']->id,
                'received_date' => now()->subMonths(13)->toDateString(),
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // The properties
    // ---------------------------------------------------------------------

    /** Held in stock, half funded by an investor. */
    private function availablePlotWithInvestor(): void
    {
        $property = $this->property('SHE-001', '5 Marla residential plot', [
            'project_name' => 'Bahria Town Phase 8',
            'block' => 'Block C',
            'plot_number' => '114',
            'city' => 'Rawalpindi',
            'property_type' => 'plot',
            'size' => 5,
            'size_unit' => 'marla',
        ]);

        $this->purchase($property, '4500000', now()->subMonths(11), [[0, '4500000']], payNow: '4500000');
        $this->expense($property, 'documentation-transfer', '180000', now()->subMonths(11), pay: true);
        $this->expense($property, 'purchase-taxes', '70000', now()->subMonths(11), pay: true);

        // Cost base is now 4,750,000. The investor takes exactly half.
        app(AllocationService::class)->allocate($property->fresh(), [
            ['investor_id' => $this->investors['imran']->id, 'capital_amount' => '2375000'],
        ], now()->subMonths(11)->toDateString());

        $this->expense($property, 'security-caretaking', '36000', now()->subMonths(4), pay: true);
    }

    /** Agreed and being paid for in installments, not yet finalised. */
    private function plotUnderSaleWithInstallments(): void
    {
        $property = $this->property('SHE-002', '1 Kanal corner plot', [
            'project_name' => 'DHA Phase 6',
            'block' => 'Block K',
            'plot_number' => '7',
            'city' => 'Lahore',
            'property_type' => 'plot',
            'size' => 1,
            'size_unit' => 'kanal',
        ]);

        $this->purchase($property, '8000000', now()->subMonths(9), [[1, '8000000']], payNow: '6000000');
        $this->expense($property, 'documentation-transfer', '320000', now()->subMonths(9), pay: true);
        $this->expense($property, 'purchase-commission', '80000', now()->subMonths(9), pay: false);

        // Cost base 8,400,000. Ownership follows the money, but the agreed
        // profit split is deliberately different.
        app(AllocationService::class)->allocate($property->fresh(), [
            ['investor_id' => $this->investors['sana']->id, 'capital_amount' => '4200000', 'ownership_percentage' => '50', 'profit_share_percentage' => '45'],
            ['investor_id' => $this->investors['imran']->id, 'capital_amount' => '2100000', 'ownership_percentage' => '25', 'profit_share_percentage' => '25'],
        ], now()->subMonths(9)->toDateString());

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[0]->id,
            'agreement_date' => now()->subMonths(2)->toDateString(),
            'sale_price' => '10500000',
            'status' => Sale::STATUS_DRAFT,
            'notes' => 'Payable in four quarterly installments after the booking amount.',
            'created_by' => $this->owner->id,
        ]);

        $property->update(['status' => Property::STATUS_UNDER_SALE]);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $sale->buyer_id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(2)->toDateString(),
            'amount' => '2500000',
            'type' => BuyerPayment::TYPE_ADVANCE,
            'method' => 'bank_transfer',
            'reference' => 'Booking amount',
        ]);

        app(BuyerPaymentService::class)->setSchedule($sale, [
            ['due_date' => now()->subMonth()->toDateString(), 'amount' => '2000000', 'notes' => 'First quarter'],
            ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => '2000000', 'notes' => 'Second quarter'],
            ['due_date' => now()->addMonths(5)->toDateString(), 'amount' => '2000000', 'notes' => 'Third quarter'],
            ['due_date' => now()->addMonths(8)->toDateString(), 'amount' => '2000000', 'notes' => 'On transfer'],
        ]);

        // The first installment is overdue and only part paid, so the
        // dashboard and the overdue report have something real to show.
        $first = $sale->installments()->first();

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $sale->buyer_id,
            'installment_id' => $first->id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subWeeks(3)->toDateString(),
            'amount' => '750000',
            'type' => BuyerPayment::TYPE_INSTALLMENT,
            'method' => 'cheque',
            'reference' => 'Cheque 884120',
        ]);
    }

    /** Closed deal, profit split three ways, only partly distributed. */
    private function soldWithInvestorProfitPartlyPaid(): void
    {
        $property = $this->property('SHE-003', '10 Marla house', [
            'project_name' => 'Johar Town',
            'block' => 'Block H',
            'plot_number' => '221',
            'city' => 'Lahore',
            'property_type' => 'house',
            'size' => 10,
            'size_unit' => 'marla',
        ]);

        $this->purchase($property, '3000000', now()->subMonths(12), [[2, '3000000']], payNow: '3000000');
        $this->expense($property, 'documentation-transfer', '90000', now()->subMonths(12), pay: true);
        $this->expense($property, 'development-work', '60000', now()->subMonths(10), pay: true);
        $this->expense($property, 'repairs-maintenance', '45000', now()->subMonths(8), pay: true);

        // Cost base 3,150,000. Profit is shared differently from ownership.
        app(AllocationService::class)->allocate($property->fresh(), [
            ['investor_id' => $this->investors['bilal']->id, 'capital_amount' => '2650000', 'ownership_percentage' => '84', 'profit_share_percentage' => '70'],
        ], now()->subMonths(12)->toDateString());

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[1]->id,
            'agreement_date' => now()->subMonths(3)->toDateString(),
            'sale_price' => '4200000',
            'status' => Sale::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        $this->expense($property, 'sale-commission', '84000', now()->subMonths(3), pay: true, saleId: $sale->id);
        $this->expense($property, 'sale-transfer-charges', '46000', now()->subMonths(3), pay: true, saleId: $sale->id);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $sale->buyer_id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(3)->toDateString(),
            'amount' => '4200000',
            'type' => BuyerPayment::TYPE_ADVANCE,
            'method' => 'bank_transfer',
        ]);

        $sale = app(SaleService::class)->finalize($sale, now()->subMonths(3)->toDateString());

        // Pay the investor most, but not all, of what was calculated, so the
        // difference between payable and paid is visible.
        $payable = $sale->profitPayables()->where('is_owner', false)->first();

        if ($payable) {
            app(DistributionService::class)->pay($payable, [
                'financial_account_id' => $this->accounts['bank']->id,
                'payment_date' => now()->subMonths(2)->toDateString(),
                'amount' => '400000',
                'method' => 'bank_transfer',
                'reference' => 'Part payment of profit share',
            ]);
        }
    }

    /** Bought and sold with no outside investors at all. */
    private function soldOutrightNoInvestors(): void
    {
        $property = $this->property('SHE-004', 'Commercial shop, ground floor', [
            'project_name' => 'Gulberg Galleria',
            'plot_number' => 'G-12',
            'city' => 'Lahore',
            'property_type' => 'shop',
            'size' => 450,
            'size_unit' => 'sq ft',
        ]);

        $this->purchase($property, '2200000', now()->subMonths(7), [[0, '2200000']], payNow: '2200000');

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[2]->id,
            'agreement_date' => now()->subMonth()->toDateString(),
            'sale_price' => '2750000',
            'status' => Sale::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        $this->expense($property, 'sale-commission', '55000', now()->subMonth(), pay: true, saleId: $sale->id);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $sale->buyer_id,
            'financial_account_id' => $this->accounts['cash']->id,
            'payment_date' => now()->subMonth()->toDateString(),
            'amount' => '2750000',
            'type' => BuyerPayment::TYPE_FINAL,
            'method' => 'cash',
        ]);

        app(SaleService::class)->finalize($sale, now()->subMonth()->toDateString());
    }

    /** Token taken, held as a liability, not yet converted to a sale. */
    private function reservedWithToken(): void
    {
        $property = $this->property('SHE-005', '2 Kanal farmhouse plot', [
            'project_name' => 'Bedian Road',
            'city' => 'Lahore',
            'property_type' => 'agricultural',
            'size' => 2,
            'size_unit' => 'kanal',
        ]);

        $this->purchase($property, '6000000', now()->subMonths(5), [[1, '6000000']], payNow: '4000000');

        $reservation = Reservation::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[3]->id,
            'reservation_date' => now()->subWeeks(2)->toDateString(),
            'token_amount' => '500000',
            'agreed_price' => '7400000',
            'expiry_date' => now()->addWeeks(2)->toDateString(),
            'status' => Reservation::STATUS_ACTIVE,
            'notes' => 'Buyer arranging bank finance.',
            'created_by' => $this->owner->id,
        ]);

        $property->update(['status' => Property::STATUS_RESERVED]);

        app(BuyerPaymentService::class)->record([
            'reservation_id' => $reservation->id,
            'buyer_id' => $reservation->buyer_id,
            'financial_account_id' => $this->accounts['cash']->id,
            'payment_date' => $reservation->reservation_date->toDateString(),
            'amount' => '500000',
            'type' => BuyerPayment::TYPE_TOKEN,
            'method' => 'cash',
        ]);
    }

    /** In stock with money still owed to the seller. */
    private function purchaseStillOwingTheSeller(): void
    {
        $property = $this->property('SHE-006', '3 Marla shop plot', [
            'project_name' => 'Ferozepur Road',
            'city' => 'Lahore',
            'property_type' => 'commercial',
            'size' => 3,
            'size_unit' => 'marla',
        ]);

        // Two sellers on one deal, paid unevenly so far.
        $purchase = $this->purchase($property, '1800000', now()->subMonths(3), [
            [0, '1000000'],
            [2, '800000'],
        ]);

        app(PurchaseService::class)->recordPayment($purchase, [
            'seller_id' => $this->sellers[0]->id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(3)->toDateString(),
            'amount' => '1000000',
            'method' => 'bank_transfer',
        ]);

        app(PurchaseService::class)->recordPayment($purchase, [
            'seller_id' => $this->sellers[2]->id,
            'financial_account_id' => $this->accounts['cash']->id,
            'payment_date' => now()->subMonths(2)->toDateString(),
            'amount' => '300000',
            'method' => 'cash',
            'notes' => 'Part payment, balance on transfer',
        ]);

        $this->expense($property, 'property-tax-utilities', '18000', now()->subMonth(), pay: false);
    }

    /**
     * A sale that fell through and a later, separate sale of the same
     * property. Both records survive; the second is not an overwrite.
     */
    private function cancelledSaleThenResold(): void
    {
        $property = $this->property('SHE-007', '1 Kanal house', [
            'project_name' => 'Model Town',
            'block' => 'Block B',
            'city' => 'Lahore',
            'property_type' => 'house',
            'size' => 1,
            'size_unit' => 'kanal',
        ]);

        $this->purchase($property, '5000000', now()->subMonths(10), [[2, '5000000']], payNow: '5000000');

        $first = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[3]->id,
            'agreement_date' => now()->subMonths(6)->toDateString(),
            'sale_price' => '6200000',
            'status' => Sale::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $first->id,
            'buyer_id' => $first->buyer_id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(6)->toDateString(),
            'amount' => '600000',
            'type' => BuyerPayment::TYPE_TOKEN,
            'method' => 'bank_transfer',
        ]);

        $first = app(SaleService::class)->finalize($first, now()->subMonths(6)->toDateString());

        app(SaleService::class)->cancel($first, [
            'cancellation_reason' => 'Buyer could not arrange the balance within the agreed time.',
            'cancellation_date' => now()->subMonths(5)->toDateString(),
            'forfeited_amount' => '150000',
            'return_to_available' => true,
        ]);

        // Refund what was not forfeited.
        app(BuyerPaymentService::class)->record([
            'sale_id' => $first->id,
            'buyer_id' => $first->buyer_id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(5)->toDateString(),
            'amount' => '450000',
            'type' => BuyerPayment::TYPE_REFUND,
            'method' => 'bank_transfer',
            'reference' => 'Refund after cancellation charge',
        ]);

        // A completely new sale record for the same property.
        $second = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $this->buyers[1]->id,
            'agreement_date' => now()->subMonths(4)->toDateString(),
            'sale_price' => '6500000',
            'supersedes_sale_id' => $first->id,
            'status' => Sale::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        $this->expense($property, 'sale-commission', '130000', now()->subMonths(4), pay: true, saleId: $second->id);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $second->id,
            'buyer_id' => $second->buyer_id,
            'financial_account_id' => $this->accounts['bank']->id,
            'payment_date' => now()->subMonths(4)->toDateString(),
            'amount' => '4000000',
            'type' => BuyerPayment::TYPE_ADVANCE,
            'method' => 'bank_transfer',
        ]);

        app(SaleService::class)->finalize($second, now()->subMonths(4)->toDateString());
    }

    /** Above the approval threshold, raised by staff, waiting for a decision. */
    private function purchaseWaitingForApproval(): void
    {
        $property = $this->property('SHE-008', '4 Kanal commercial plot', [
            'project_name' => 'Raiwind Road',
            'city' => 'Lahore',
            'property_type' => 'commercial',
            'size' => 4,
            'size_unit' => 'kanal',
        ]);

        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'purchase_date' => now()->toDateString(),
            'purchase_price' => '12500000',
            'status' => PropertyPurchase::STATUS_DRAFT,
            'notes' => 'Large plot, above the approval threshold.',
            'created_by' => $this->manager->id,
        ]);

        PropertyPurchaseSeller::create([
            'property_purchase_id' => $purchase->id,
            'seller_id' => $this->sellers[1]->id,
            'amount' => '12500000',
        ]);

        // Raised by the manager, so the account holder has something to decide.
        Auth::login($this->manager);

        app(\App\Services\Approvals\ApprovalManager::class)->request(
            ApprovalRule::ACTION_PROPERTY_PURCHASE,
            $purchase,
            \App\Support\Money::of($purchase->purchase_price),
            'Seller wants an answer this week.',
        );

        $purchase->update(['status' => PropertyPurchase::STATUS_PENDING_APPROVAL]);

        Auth::login($this->owner);
    }

    // ---------------------------------------------------------------------
    // Small helpers
    // ---------------------------------------------------------------------

    private function property(string $reference, string $title, array $attributes): Property
    {
        return Property::create(array_merge([
            'reference' => $reference,
            'title' => $title,
            'status' => Property::STATUS_AVAILABLE,
            'created_by' => $this->owner->id,
        ], $attributes));
    }

    /** @param list<array{0:int,1:string}> $sellerShares */
    private function purchase(
        Property $property,
        string $price,
        \DateTimeInterface $date,
        array $sellerShares,
        ?string $payNow = null,
    ): PropertyPurchase {
        $purchase = PropertyPurchase::create([
            'property_id' => $property->id,
            'purchase_date' => $date->format('Y-m-d'),
            'purchase_price' => $price,
            'status' => PropertyPurchase::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        foreach ($sellerShares as [$sellerIndex, $amount]) {
            PropertyPurchaseSeller::create([
                'property_purchase_id' => $purchase->id,
                'seller_id' => $this->sellers[$sellerIndex]->id,
                'amount' => $amount,
            ]);
        }

        app(PurchaseService::class)->confirm($purchase->fresh()->load('sellerShares', 'property'));

        if ($payNow !== null) {
            app(PurchaseService::class)->recordPayment($purchase->fresh()->load('property'), [
                'seller_id' => count($sellerShares) === 1 ? $this->sellers[$sellerShares[0][0]]->id : null,
                'financial_account_id' => $this->accounts['bank']->id,
                'payment_date' => $date->format('Y-m-d'),
                'amount' => $payNow,
                'method' => 'bank_transfer',
            ]);
        }

        return $purchase->fresh();
    }

    private function expense(
        Property $property,
        string $categorySlug,
        string $amount,
        \DateTimeInterface $date,
        bool $pay = false,
        ?int $saleId = null,
    ): Expense {
        $category = ExpenseCategory::where('slug', $categorySlug)->firstOrFail();

        $expense = Expense::create([
            'property_id' => $property->id,
            'sale_id' => $saleId,
            'expense_category_id' => $category->id,
            'expense_date' => $date->format('Y-m-d'),
            'amount' => $amount,
            'description' => $category->name,
            'status' => Expense::STATUS_DRAFT,
            'created_by' => $this->owner->id,
        ]);

        app(ExpenseService::class)->confirm($expense->fresh()->load('category', 'sale'));

        if ($pay) {
            app(ExpenseService::class)->recordPayment($expense->fresh()->load('category', 'sale'), [
                'financial_account_id' => $this->accounts['bank']->id,
                'payment_date' => $date->format('Y-m-d'),
                'amount' => $amount,
                'method' => 'bank_transfer',
            ]);
        }

        return $expense->fresh();
    }

    private function report(): void
    {
        $ledger = app(LedgerQuery::class);
        $summary = $ledger->summary();

        $this->command?->newLine();
        $this->command?->info('Demo workspace ready.');
        $this->command?->table(
            ['Figure', 'Amount (PKR)'],
            [
                ['Cash and bank', $summary['cash']->format(false)],
                ['Unsold property cost', $summary['property_cost']->format(false)],
                ['Buyer receivables', $summary['buyer_receivable']->format(false)],
                ['Seller payables', $summary['seller_payable']->format(false)],
                ['Buyer advances held', $summary['buyer_advance']->format(false)],
                ['Investor capital', $summary['investor_capital']->format(false)],
                ['Investor profit payable', $summary['investor_profit_payable']->format(false)],
                ['Realised profit on sales', \App\Support\Money::of(Sale::finalized()->sum('realized_profit'))->format(false)],
                ['Trial balance difference', $ledger->trialBalanceDifference()->format(false)],
            ],
        );

        $this->command?->newLine();
        $this->command?->line('Sign in with any of these (password: <options=bold>password</>):');
        $this->command?->line('  owner@demo.test     Account holder');
        $this->command?->line('  manager@demo.test   Staff, admin preset');
        $this->command?->line('  staff@demo.test     Staff, limited preset');
        $this->command?->line('  investor@demo.test  Investor portal, read only');
    }
}
