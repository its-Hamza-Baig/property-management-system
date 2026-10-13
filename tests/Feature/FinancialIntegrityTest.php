<?php

namespace Tests\Feature;

use App\Models\BuyerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Sale;
use App\Services\Cash\CashService;
use App\Services\Expenses\ExpenseService;
use App\Services\Investors\AllocationService;
use App\Services\Investors\DistributionService;
use App\Services\Ledger\LedgerQuery;
use App\Services\Purchases\PurchaseService;
use App\Services\Sales\BuyerPaymentService;
use App\Services\Sales\SaleService;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The double-counting rules from the specification, each as an executable
 * check. These are the tests that decide whether the money is right.
 */
class FinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private LedgerQuery $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        ['tenant' => $tenant, 'owner' => $owner] = $this->makeWorkspace();
        $this->actingInWorkspace($tenant, $owner);
        $this->ledger = app(LedgerQuery::class);
    }

    public function test_purchase_price_is_counted_as_cost_exactly_once(): void
    {
        $property = $this->makePurchasedProperty('1000000');

        $this->assertSame('1000000.00', $this->ledger->balance(LedgerAccounts::PROPERTY_COST)->toDecimal());
        $this->assertSame('1000000.00', $this->ledger->balance(LedgerAccounts::SELLER_PAYABLE)->toDecimal());
        $this->assertSame('1000000.00', $property->totalCost()->toDecimal());
    }

    public function test_seller_installments_settle_the_payable_without_adding_cost_again(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '800000');
        $purchase = $property->purchase;

        app(PurchaseService::class)->recordPayment($purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '300000',
        ]);
        app(PurchaseService::class)->recordPayment($purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '200000',
        ]);

        // Cost unchanged after two payments.
        $this->assertSame('1000000.00', $this->ledger->balance(LedgerAccounts::PROPERTY_COST)->toDecimal());
        $this->assertSame('500000.00', $this->ledger->balance(LedgerAccounts::SELLER_PAYABLE)->toDecimal());
        $this->assertSame('500000.00', $purchase->fresh()->outstandingPayable()->toDecimal());
        $this->assertSame('300000.00', $account->currentBalance()->toDecimal());
    }

    public function test_a_payment_larger_than_the_outstanding_payable_is_refused(): void
    {
        $property = $this->makePurchasedProperty('500000');
        $account = $this->makeCashAccount('Cash', '900000');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('more than the outstanding balance');

        app(PurchaseService::class)->recordPayment($property->purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '600000',
        ]);
    }

    public function test_expense_recognition_and_expense_payment_are_not_double_counted(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '500000');
        $category = ExpenseCategory::where('slug', 'repairs-maintenance')->firstOrFail();

        $expense = Expense::create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '50000',
            'description' => 'Boundary wall repair',
            'status' => Expense::STATUS_DRAFT,
        ]);

        app(ExpenseService::class)->confirm($expense);

        $this->assertSame('50000.00', $this->ledger->totalExpenses()->toDecimal());
        $this->assertSame('50000.00', $this->ledger->balance(LedgerAccounts::EXPENSE_PAYABLE)->toDecimal());

        app(ExpenseService::class)->recordPayment($expense->fresh(), [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '50000',
        ]);

        // Paying it clears the payable. The expense itself is still 50,000,
        // not 100,000.
        $this->assertSame('50000.00', $this->ledger->totalExpenses()->toDecimal());
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::EXPENSE_PAYABLE)->toDecimal());
        $this->assertSame('450000.00', $account->currentBalance()->toDecimal());
    }

    public function test_capitalised_expenses_raise_property_cost_instead_of_profit(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $category = ExpenseCategory::where('slug', 'documentation-transfer')->firstOrFail();

        $expense = Expense::create([
            'property_id' => $property->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '75000',
            'status' => Expense::STATUS_DRAFT,
        ]);

        app(ExpenseService::class)->confirm($expense);

        $this->assertSame('1075000.00', $property->fresh()->totalCost()->toDecimal());
        $this->assertSame('0.00', $this->ledger->totalExpenses()->toDecimal());
    }

    public function test_a_transfer_between_accounts_is_neither_income_nor_expense(): void
    {
        $cash = $this->makeCashAccount('Cash in hand', '200000');
        $bank = $this->makeCashAccount('Bank', '0');

        app(CashService::class)->transfer([
            'from_financial_account_id' => $cash->id,
            'to_financial_account_id' => $bank->id,
            'transfer_date' => now()->toDateString(),
            'amount' => '150000',
        ]);

        $this->assertSame('50000.00', $cash->currentBalance()->toDecimal());
        $this->assertSame('150000.00', $bank->currentBalance()->toDecimal());
        // Total cash is unchanged, and nothing landed in income or expense.
        $this->assertSame('200000.00', $this->ledger->totalCash()->toDecimal());
        $this->assertSame('0.00', $this->ledger->totalIncome()->toDecimal());
        $this->assertSame('0.00', $this->ledger->totalExpenses()->toDecimal());
    }

    public function test_a_buyer_token_is_a_liability_and_not_profit(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '0');
        $buyer = $this->makeBuyer();

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '200000',
            'type' => BuyerPayment::TYPE_TOKEN,
        ]);

        $this->assertSame('200000.00', $account->currentBalance()->toDecimal());
        $this->assertSame('200000.00', $this->ledger->balance(LedgerAccounts::BUYER_ADVANCE)->toDecimal());
        $this->assertSame('0.00', $this->ledger->totalIncome()->toDecimal());
        $this->assertSame('0.00', $this->ledger->netResult()->toDecimal());
    }

    public function test_realised_profit_is_separate_from_cash_received(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '0');
        $buyer = $this->makeBuyer();
        $category = ExpenseCategory::where('slug', 'sale-commission')->firstOrFail();

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        $commission = Expense::create([
            'property_id' => $property->id,
            'sale_id' => $sale->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '40000',
            'status' => Expense::STATUS_DRAFT,
        ]);
        app(ExpenseService::class)->confirm($commission);

        // Buyer pays only a part before the deal closes.
        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '400000',
            'type' => BuyerPayment::TYPE_ADVANCE,
        ]);

        $sale = app(SaleService::class)->finalize($sale->fresh());

        // 1,400,000 - 1,000,000 cost - 40,000 sale expense
        $this->assertSame('360000.00', $sale->realized_profit);
        $this->assertSame('1000000.00', $sale->allocated_cost);

        // Cash actually in hand is only what the buyer paid.
        $this->assertSame('400000.00', $account->currentBalance()->toDecimal());
        // And a million is still owed.
        $this->assertSame('1000000.00', $sale->outstandingReceivable()->toDecimal());
        $this->assertSame('1000000.00', $this->ledger->balance(LedgerAccounts::BUYER_RECEIVABLE)->toDecimal());
        // The advance was absorbed into the receivable, not left double counted.
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::BUYER_ADVANCE)->toDecimal());
        $this->assertSame(Property::STATUS_SOLD, $property->fresh()->status);
    }

    public function test_a_sale_paid_in_full_before_finalisation_leaves_nothing_outstanding(): void
    {
        $property = $this->makePurchasedProperty('2200000');
        $account = $this->makeCashAccount('Cash', '0');
        $buyer = $this->makeBuyer();

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '2750000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        // The buyer settles the whole amount before the paperwork completes.
        // Whatever the receipt is called, it is held as an advance until the
        // sale is finalised.
        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '2750000',
            'type' => BuyerPayment::TYPE_FINAL,
        ]);

        $this->assertSame('2750000.00', $this->ledger->balance(LedgerAccounts::BUYER_ADVANCE)->toDecimal());

        $sale = app(SaleService::class)->finalize($sale);

        // Finalisation must absorb the whole advance. Leaving any behind
        // would show the sale as both fully paid and still owing.
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::BUYER_ADVANCE)->toDecimal());
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::BUYER_RECEIVABLE)->toDecimal());
        $this->assertSame('0.00', $sale->outstandingReceivable()->toDecimal());
        $this->assertSame('2750000.00', $sale->receivedTotal()->toDecimal());
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_cancelling_after_part_payment_puts_the_money_back_as_refundable(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '0');
        $buyer = $this->makeBuyer();

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        // Money arrives after finalisation, so it clears the receivable.
        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '300000',
            'type' => BuyerPayment::TYPE_INSTALLMENT,
        ]);

        $this->assertSame('1100000.00', $this->ledger->balance(LedgerAccounts::BUYER_RECEIVABLE)->toDecimal());

        app(SaleService::class)->cancel($sale, [
            'cancellation_reason' => 'Buyer withdrew after paying one installment',
            'forfeited_amount' => '50000',
        ]);

        // The receivable is gone entirely, and what the buyer paid is held as
        // a liability again rather than showing as a negative receivable.
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::BUYER_RECEIVABLE)->toDecimal());
        $this->assertSame('250000.00', $this->ledger->balance(LedgerAccounts::BUYER_ADVANCE)->toDecimal());
        $this->assertSame('50000.00', $this->ledger->balance(LedgerAccounts::FORFEITURE_INCOME)->toDecimal());
        $this->assertSame('250000.00', app(SaleService::class)->refundDueFor($sale->fresh())->toDecimal());
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_investor_distribution_is_not_a_property_expense(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '2000000');
        $investor = $this->makeInvestor('Bilal');
        $buyer = $this->makeBuyer();

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '600000'],
        ], now()->toDateString());

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1500000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        $sale = app(SaleService::class)->finalize($sale);

        $this->assertSame('500000.00', $sale->realized_profit);

        $payable = $sale->profitPayables()->where('investor_id', $investor->id)->firstOrFail();
        // 60% of the cost base was the investor capital, so 60% of profit.
        $this->assertSame('300000.00', $payable->profit_amount);
        $this->assertSame('0.00', $payable->paid_amount);

        $expensesBefore = $this->ledger->totalExpenses();

        app(DistributionService::class)->pay($payable, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '300000',
        ]);

        $payable->refresh();
        $this->assertSame('300000.00', $payable->paid_amount);
        $this->assertSame('0.00', $payable->outstanding()->toDecimal());
        // Paying the investor did not create an expense.
        $this->assertSame($expensesBefore->toDecimal(), $this->ledger->totalExpenses()->toDecimal());
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::INVESTOR_PROFIT_PAYABLE)->toDecimal());
    }

    public function test_profit_payable_and_profit_paid_stay_separate(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '2000000');
        $investor = $this->makeInvestor('Sana');
        $buyer = $this->makeBuyer();

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '1000000'],
        ], now()->toDateString());

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1200000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        $payable = $sale->profitPayables()->where('investor_id', $investor->id)->firstOrFail();
        $this->assertSame('200000.00', $payable->profit_amount);

        app(DistributionService::class)->pay($payable, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '50000',
        ]);

        $payable->refresh();
        $this->assertSame('200000.00', $payable->profit_amount);
        $this->assertSame('50000.00', $payable->paid_amount);
        $this->assertSame('150000.00', $payable->outstanding()->toDecimal());
    }

    public function test_the_books_always_balance(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '2000000');
        $investor = $this->makeInvestor();
        $buyer = $this->makeBuyer();

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '400000'],
        ], now()->toDateString());

        app(PurchaseService::class)->recordPayment($property->purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '1000000',
        ]);

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1333333',
            'status' => Sale::STATUS_DRAFT,
        ]));

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '1333333',
            'type' => BuyerPayment::TYPE_INSTALLMENT,
        ]);

        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_profit_shares_add_back_to_the_exact_total(): void
    {
        $property = $this->makePurchasedProperty('999999');
        $a = $this->makeInvestor('A');
        $b = $this->makeInvestor('B');
        $c = $this->makeInvestor('C');
        $buyer = $this->makeBuyer();

        // Three equal shares of a number that does not divide evenly.
        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $a->id, 'capital_amount' => '333333', 'profit_share_percentage' => '33.3333'],
            ['investor_id' => $b->id, 'capital_amount' => '333333', 'profit_share_percentage' => '33.3333'],
            ['investor_id' => $c->id, 'capital_amount' => '333333', 'profit_share_percentage' => '33.3334'],
        ], now()->toDateString());

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1100000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        $total = Money::sum($sale->profitPayables()->pluck('profit_amount'));

        $this->assertSame($sale->realized_profit, $total->toDecimal());
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_investor_capital_cannot_exceed_the_property_cost(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $investor = $this->makeInvestor();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('more than the total property cost');

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '1200000'],
        ], now()->toDateString());
    }

    public function test_ownership_and_profit_percentages_are_validated_separately(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $a = $this->makeInvestor('A');
        $b = $this->makeInvestor('B');

        // Ownership follows the money, profit sharing does not. Both are
        // accepted as long as each totals 100 on its own.
        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $a->id, 'capital_amount' => '700000', 'ownership_percentage' => '70', 'profit_share_percentage' => '50'],
            ['investor_id' => $b->id, 'capital_amount' => '300000', 'ownership_percentage' => '30', 'profit_share_percentage' => '50'],
        ], now()->toDateString());

        $allocations = $property->allocations()->active()->get();

        $this->assertEqualsWithDelta(100, $allocations->sum(fn ($a) => (float) $a->ownership_percentage), 0.01);
        $this->assertEqualsWithDelta(100, $allocations->sum(fn ($a) => (float) $a->profit_share_percentage), 0.01);
        $this->assertSame('70.0000', $allocations->firstWhere('investor_id', $a->id)->ownership_percentage);
        $this->assertSame('50.0000', $allocations->firstWhere('investor_id', $a->id)->profit_share_percentage);
    }

    public function test_a_reversal_creates_a_linked_entry_and_leaves_the_original(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '1000000');

        $payment = app(PurchaseService::class)->recordPayment($property->purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '400000',
        ]);

        $originalJournalId = $payment->journal_id;

        app(PurchaseService::class)->reversePayment($payment, 'Paid to the wrong seller');

        // Original journal is still there, untouched.
        $this->assertDatabaseHas('journals', ['id' => $originalJournalId]);
        $this->assertDatabaseHas('journals', ['reversal_of_id' => $originalJournalId]);
        // And the balances are back where they started.
        $this->assertSame('1000000.00', $account->currentBalance()->toDecimal());
        $this->assertSame('1000000.00', $property->purchase->fresh()->outstandingPayable()->toDecimal());
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_a_duplicate_submission_cannot_create_two_payments(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '1000000');
        $key = 'form-token-123';

        app(PurchaseService::class)->recordPayment($property->purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '100000',
            'idempotency_key' => $key,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        app(PurchaseService::class)->recordPayment($property->purchase, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '100000',
            'idempotency_key' => $key,
        ]);
    }

    public function test_a_cancelled_sale_keeps_its_history_and_records_the_settlement(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '0');
        $buyer = $this->makeBuyer();

        $sale = Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]);

        app(BuyerPaymentService::class)->record([
            'sale_id' => $sale->id,
            'buyer_id' => $buyer->id,
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '200000',
            'type' => BuyerPayment::TYPE_TOKEN,
        ]);

        $sale = app(SaleService::class)->finalize($sale->fresh());

        $sale = app(SaleService::class)->cancel($sale, [
            'cancellation_reason' => 'Buyer withdrew',
            'forfeited_amount' => '50000',
            'cancellation_date' => now()->toDateString(),
        ]);

        $this->assertSame(Sale::STATUS_CANCELLED, $sale->status);
        $this->assertSame('Buyer withdrew', $sale->cancellation_reason);
        // The sale row survives.
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => Sale::STATUS_CANCELLED]);
        // Revenue and the receivable are gone, the property is back on the shelf.
        $this->assertSame('0.00', $this->ledger->balance(LedgerAccounts::BUYER_RECEIVABLE)->toDecimal());
        $this->assertSame('1000000.00', $this->ledger->balance(LedgerAccounts::PROPERTY_COST)->toDecimal());
        $this->assertSame(Property::STATUS_AVAILABLE, $property->fresh()->status);
        // The forfeited part became income, the rest is still owed back.
        $this->assertSame('50000.00', $this->ledger->balance(LedgerAccounts::FORFEITURE_INCOME)->toDecimal());
        $this->assertSame('150000.00', app(SaleService::class)->refundDueFor($sale)->toDecimal());
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_a_correction_preserves_the_original_and_adjusts_only_what_is_unpaid(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $account = $this->makeCashAccount('Cash', '2000000');
        $investor = $this->makeInvestor();
        $buyer = $this->makeBuyer();

        app(AllocationService::class)->allocate($property, [
            ['investor_id' => $investor->id, 'capital_amount' => '1000000'],
        ], now()->toDateString());

        $sale = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1300000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        $payable = $sale->profitPayables()->where('investor_id', $investor->id)->firstOrFail();
        $this->assertSame('300000.00', $payable->profit_amount);

        app(DistributionService::class)->pay($payable, [
            'financial_account_id' => $account->id,
            'payment_date' => now()->toDateString(),
            'amount' => '100000',
        ]);

        // The agreed price was actually 1,250,000.
        $sale = app(SaleService::class)->correct($sale, ['sale_price' => '1250000'], 'Agreement price was recorded incorrectly');

        $this->assertSame('250000.00', $sale->realized_profit);

        $payable->refresh();
        // The 100,000 already handed over is untouched; only the balance moved.
        $this->assertSame('100000.00', $payable->paid_amount);
        $this->assertSame('250000.00', $payable->profit_amount);
        $this->assertSame('150000.00', $payable->outstanding()->toDecimal());

        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.corrected']);
        $this->assertDatabaseHas('journals', ['is_adjustment' => true]);
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }

    public function test_a_resale_after_cancellation_is_a_new_record(): void
    {
        $property = $this->makePurchasedProperty('1000000');
        $buyer = $this->makeBuyer('First buyer');
        $secondBuyer = $this->makeBuyer('Second buyer');

        $first = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1400000',
            'status' => Sale::STATUS_DRAFT,
        ]));

        app(SaleService::class)->cancel($first, ['cancellation_reason' => 'Fell through']);

        $second = app(SaleService::class)->finalize(Sale::create([
            'property_id' => $property->id,
            'buyer_id' => $secondBuyer->id,
            'agreement_date' => now()->toDateString(),
            'sale_price' => '1450000',
            'supersedes_sale_id' => $first->id,
            'status' => Sale::STATUS_DRAFT,
        ]));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Sale::withTrashed()->where('property_id', $property->id)->count());
        $this->assertSame('450000.00', $second->realized_profit);
        $this->assertSame('0.00', $this->ledger->trialBalanceDifference()->toDecimal());
    }
}
