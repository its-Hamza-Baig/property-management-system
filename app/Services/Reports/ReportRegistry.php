<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Installment;
use App\Models\Investor;
use App\Models\InvestorProfitPayable;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Sale;
use App\Models\FinancialAccount;
use App\Services\Ledger\LedgerQuery;
use App\Support\Money;

/**
 * The twelve reports the specification asks for.
 *
 * Each one returns plain rows and column definitions, so the same data drives
 * the on-screen table, the CSV and the PDF. Every figure is read from the
 * ledger or from a service; nothing is recalculated in a view.
 */
class ReportRegistry
{
    public function __construct(private readonly LedgerQuery $ledger)
    {
    }

    /** @return array<string, array{title:string, description:string}> */
    public function all(): array
    {
        return [
            'property-status' => [
                'title' => 'Property list and status',
                'description' => 'Every property with its current status, cost and sale outcome.',
            ],
            'property-ledger' => [
                'title' => 'Property-wise financial ledger',
                'description' => 'Cost, expenses, receipts and realised result for each property.',
            ],
            'purchase-payments' => [
                'title' => 'Purchase and seller payment report',
                'description' => 'What each property cost, what has been paid to sellers and what is still owed.',
            ],
            'sale-receivables' => [
                'title' => 'Sale and buyer receivable report',
                'description' => 'Sale values against cash actually received, with the balance still due.',
            ],
            'expenses' => [
                'title' => 'Expense report',
                'description' => 'Expenses by category, separating the amount incurred from the amount paid.',
            ],
            'realized-profit' => [
                'title' => 'Realised profit and loss',
                'description' => 'Closed sales only, with the cost and expenses that were applied to each.',
            ],
            'investor-investments' => [
                'title' => 'Investor investment statement',
                'description' => 'Capital placed by each investor and the share they hold.',
            ],
            'investor-distributions' => [
                'title' => 'Investor profit distribution statement',
                'description' => 'Profit calculated for each investor, what has been paid and what is outstanding.',
            ],
            'cash-summary' => [
                'title' => 'Cash and bank summary',
                'description' => 'Balance of every account, with the total available.',
            ],
            'seller-outstanding' => [
                'title' => 'Seller outstanding and payables',
                'description' => 'Amounts still owed to sellers on confirmed purchases.',
            ],
            'buyer-overdue' => [
                'title' => 'Buyer overdue and installment report',
                'description' => 'Installment schedule status, with overdue amounts highlighted.',
            ],
            'date-wise-financial' => [
                'title' => 'Date-wise financial summary',
                'description' => 'Income, costs, expenses and the net result over a chosen period.',
            ],
        ];
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function definition(string $key): array
    {
        return $this->all()[$key];
    }

    /**
     * @param  array{from?:string,to?:string,property_id?:int,investor_id?:int,status?:string}  $filters
     * @return array{columns:list<array{key:string,label:string,align?:string}>, rows:list<array<string,mixed>>, totals:array<string,mixed>}
     */
    public function build(string $key, array $filters = []): array
    {
        return match ($key) {
            'property-status' => $this->propertyStatus($filters),
            'property-ledger' => $this->propertyLedger($filters),
            'purchase-payments' => $this->purchasePayments($filters),
            'sale-receivables' => $this->saleReceivables($filters),
            'expenses' => $this->expenses($filters),
            'realized-profit' => $this->realizedProfit($filters),
            'investor-investments' => $this->investorInvestments($filters),
            'investor-distributions' => $this->investorDistributions($filters),
            'cash-summary' => $this->cashSummary($filters),
            'seller-outstanding' => $this->sellerOutstanding($filters),
            'buyer-overdue' => $this->buyerOverdue($filters),
            'date-wise-financial' => $this->dateWiseFinancial($filters),
        };
    }

    private function propertyStatus(array $filters): array
    {
        $properties = Property::query()
            ->with(['purchase', 'sales' => fn ($q) => $q->where('status', Sale::STATUS_FINALIZED)])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('reference')
            ->get();

        $rows = $properties->map(function (Property $property) {
            $sale = $property->sales->first();

            return [
                'reference' => $property->reference,
                'title' => $property->title,
                'type' => $property->typeLabel(),
                'city' => $property->city,
                'status' => $property->statusLabel(),
                'cost' => $property->totalCost()->toDecimal(),
                'sale_price' => $sale?->sale_price,
                'profit' => $sale?->realized_profit,
            ];
        })->all();

        return [
            'columns' => [
                ['key' => 'reference', 'label' => 'Reference'],
                ['key' => 'title', 'label' => 'Property'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'city', 'label' => 'City'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'cost', 'label' => 'Total cost', 'align' => 'money'],
                ['key' => 'sale_price', 'label' => 'Sale price', 'align' => 'money'],
                ['key' => 'profit', 'label' => 'Realised profit', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'cost' => Money::sum(array_column($rows, 'cost'))->toDecimal(),
                'sale_price' => Money::sum(array_filter(array_column($rows, 'sale_price')))->toDecimal(),
                'profit' => Money::sum(array_filter(array_column($rows, 'profit')))->toDecimal(),
            ],
        ];
    }

    private function propertyLedger(array $filters): array
    {
        $properties = Property::query()
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->with(['purchase', 'sales' => fn ($q) => $q->where('status', Sale::STATUS_FINALIZED)])
            ->orderBy('reference')
            ->get();

        $rows = $properties->map(function (Property $property) use ($filters) {
            $scope = $filters + ['property_id' => $property->id];
            $sale = $property->sales->first();

            return [
                'reference' => $property->reference,
                'purchase_price' => $property->purchase?->purchase_price ?? '0.00',
                'capitalised' => $property->capitalizedExpenseTotal()->toDecimal(),
                'period_expenses' => $this->ledger->totalExpenses($scope)->toDecimal(),
                'received' => $sale ? $sale->receivedTotal()->toDecimal() : '0.00',
                'receivable' => $sale ? $sale->outstandingReceivable()->toDecimal() : '0.00',
                'profit' => $sale?->realized_profit ?? '0.00',
            ];
        })->all();

        return [
            'columns' => [
                ['key' => 'reference', 'label' => 'Property'],
                ['key' => 'purchase_price', 'label' => 'Purchase price', 'align' => 'money'],
                ['key' => 'capitalised', 'label' => 'Capitalised expenses', 'align' => 'money'],
                ['key' => 'period_expenses', 'label' => 'Other expenses', 'align' => 'money'],
                ['key' => 'received', 'label' => 'Cash received', 'align' => 'money'],
                ['key' => 'receivable', 'label' => 'Still receivable', 'align' => 'money'],
                ['key' => 'profit', 'label' => 'Realised profit', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['purchase_price', 'capitalised', 'period_expenses', 'received', 'receivable', 'profit']),
        ];
    }

    private function purchasePayments(array $filters): array
    {
        $purchases = PropertyPurchase::query()
            ->with(['property', 'sellerShares.seller'])
            ->where('status', PropertyPurchase::STATUS_CONFIRMED)
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('purchase_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('purchase_date', '<=', $d))
            ->orderBy('purchase_date')
            ->get();

        $rows = $purchases->map(fn (PropertyPurchase $purchase) => [
            'date' => $purchase->purchase_date->toDateString(),
            'reference' => $purchase->property->reference,
            'sellers' => $purchase->sellerShares->map(fn ($s) => $s->seller->name)->join(', ') ?: '-',
            'price' => $purchase->purchase_price,
            'paid' => $purchase->paidTotal()->toDecimal(),
            'outstanding' => $purchase->outstandingPayable()->toDecimal(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Purchase date'],
                ['key' => 'reference', 'label' => 'Property'],
                ['key' => 'sellers', 'label' => 'Seller(s)'],
                ['key' => 'price', 'label' => 'Purchase price', 'align' => 'money'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'money'],
                ['key' => 'outstanding', 'label' => 'Outstanding', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['price', 'paid', 'outstanding']),
        ];
    }

    private function saleReceivables(array $filters): array
    {
        $sales = Sale::query()
            ->with(['property', 'buyer'])
            ->whereIn('status', [Sale::STATUS_FINALIZED, Sale::STATUS_APPROVED, Sale::STATUS_DRAFT])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('agreement_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('agreement_date', '<=', $d))
            ->orderBy('agreement_date')
            ->get();

        $rows = $sales->map(fn (Sale $sale) => [
            'date' => $sale->agreement_date->toDateString(),
            'reference' => $sale->property->reference,
            'buyer' => $sale->buyer->name,
            'status' => $sale->statusLabel(),
            'sale_price' => $sale->sale_price,
            'received' => $sale->receivedTotal()->toDecimal(),
            'outstanding' => $sale->outstandingReceivable()->toDecimal(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Agreement date'],
                ['key' => 'reference', 'label' => 'Property'],
                ['key' => 'buyer', 'label' => 'Buyer'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'sale_price', 'label' => 'Sale value', 'align' => 'money'],
                ['key' => 'received', 'label' => 'Cash received', 'align' => 'money'],
                ['key' => 'outstanding', 'label' => 'Still receivable', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['sale_price', 'received', 'outstanding']),
        ];
    }

    private function expenses(array $filters): array
    {
        $expenses = Expense::query()
            ->with(['category', 'property'])
            ->where('status', Expense::STATUS_CONFIRMED)
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->when($filters['property_id'] ?? null, fn ($q, $id) => $q->where('property_id', $id))
            ->orderBy('expense_date')
            ->get();

        $rows = $expenses->map(fn (Expense $expense) => [
            'date' => $expense->expense_date->toDateString(),
            'category' => $expense->category->name,
            'treatment' => $expense->is_capitalized ? 'Added to property cost' : 'Period expense',
            'property' => $expense->property?->reference ?? '-',
            'amount' => $expense->amount,
            'paid' => $expense->paidTotal()->toDecimal(),
            'outstanding' => $expense->outstandingPayable()->toDecimal(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'treatment', 'label' => 'Treatment'],
                ['key' => 'property', 'label' => 'Property'],
                ['key' => 'amount', 'label' => 'Incurred', 'align' => 'money'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'money'],
                ['key' => 'outstanding', 'label' => 'Outstanding', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['amount', 'paid', 'outstanding']),
        ];
    }

    private function realizedProfit(array $filters): array
    {
        $sales = Sale::query()
            ->with(['property', 'buyer'])
            ->finalized()
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('agreement_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('agreement_date', '<=', $d))
            ->orderBy('finalized_at')
            ->get();

        $rows = $sales->map(fn (Sale $sale) => [
            'date' => optional($sale->finalized_at)->toDateString() ?? $sale->agreement_date->toDateString(),
            'reference' => $sale->property->reference,
            'buyer' => $sale->buyer->name,
            'sale_price' => $sale->sale_price,
            'cost' => $sale->allocated_cost,
            'sale_expenses' => $sale->sale_expense_total,
            'profit' => $sale->realized_profit,
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label' => 'Finalised'],
                ['key' => 'reference', 'label' => 'Property'],
                ['key' => 'buyer', 'label' => 'Buyer'],
                ['key' => 'sale_price', 'label' => 'Sale price', 'align' => 'money'],
                ['key' => 'cost', 'label' => 'Property cost', 'align' => 'money'],
                ['key' => 'sale_expenses', 'label' => 'Sale expenses', 'align' => 'money'],
                ['key' => 'profit', 'label' => 'Realised profit', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['sale_price', 'cost', 'sale_expenses', 'profit']),
        ];
    }

    private function investorInvestments(array $filters): array
    {
        $investors = Investor::query()
            ->with(['allocations.property'])
            ->when($filters['investor_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($investors as $investor) {
            foreach ($investor->allocations->where('status', 'active') as $allocation) {
                $rows[] = [
                    'investor' => $investor->name,
                    'property' => $allocation->property->reference,
                    'property_status' => $allocation->property->statusLabel(),
                    'capital' => $allocation->capital_amount,
                    'ownership' => $allocation->ownership_percentage,
                    'profit_share' => $allocation->profit_share_percentage,
                    'since' => $allocation->effective_from?->toDateString(),
                ];
            }
        }

        return [
            'columns' => [
                ['key' => 'investor', 'label' => 'Investor'],
                ['key' => 'property', 'label' => 'Property'],
                ['key' => 'property_status', 'label' => 'Property status'],
                ['key' => 'capital', 'label' => 'Capital', 'align' => 'money'],
                ['key' => 'ownership', 'label' => 'Ownership %'],
                ['key' => 'profit_share', 'label' => 'Profit share %'],
                ['key' => 'since', 'label' => 'Effective from'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['capital']),
        ];
    }

    private function investorDistributions(array $filters): array
    {
        $payables = InvestorProfitPayable::query()
            ->with(['investor', 'sale.property'])
            ->when($filters['investor_id'] ?? null, fn ($q, $id) => $q->where('investor_id', $id))
            ->orderByDesc('id')
            ->get();

        $rows = $payables->map(fn (InvestorProfitPayable $payable) => [
            'investor' => $payable->participantName(),
            'property' => $payable->sale->property->reference,
            'share' => $payable->profit_share_percentage,
            'profit' => $payable->profit_amount,
            'paid' => $payable->paid_amount,
            'outstanding' => $payable->outstanding()->toDecimal(),
            'status' => $payable->statusLabel(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'investor', 'label' => 'Participant'],
                ['key' => 'property', 'label' => 'Property'],
                ['key' => 'share', 'label' => 'Share %'],
                ['key' => 'profit', 'label' => 'Profit calculated', 'align' => 'money'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'money'],
                ['key' => 'outstanding', 'label' => 'Outstanding', 'align' => 'money'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['profit', 'paid', 'outstanding']),
        ];
    }

    private function cashSummary(array $filters): array
    {
        $accounts = FinancialAccount::orderBy('type')->orderBy('name')->get();

        $rows = $accounts->map(fn (FinancialAccount $account) => [
            'name' => $account->displayName(),
            'type' => $account->typeLabel(),
            'opening' => $account->opening_balance,
            'balance' => $account->currentBalance()->toDecimal(),
            'status' => $account->is_active ? 'Active' : 'Inactive',
        ])->all();

        return [
            'columns' => [
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'opening', 'label' => 'Opening balance', 'align' => 'money'],
                ['key' => 'balance', 'label' => 'Current balance', 'align' => 'money'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['opening', 'balance']),
        ];
    }

    private function sellerOutstanding(array $filters): array
    {
        $purchases = PropertyPurchase::query()
            ->with(['property', 'sellerShares.seller'])
            ->where('status', PropertyPurchase::STATUS_CONFIRMED)
            ->get()
            ->filter(fn (PropertyPurchase $p) => $p->outstandingPayable()->isPositive());

        $rows = [];

        foreach ($purchases as $purchase) {
            if ($purchase->sellerShares->isEmpty()) {
                $rows[] = [
                    'property' => $purchase->property->reference,
                    'seller' => 'Not itemised',
                    'agreed' => $purchase->purchase_price,
                    'paid' => $purchase->paidTotal()->toDecimal(),
                    'outstanding' => $purchase->outstandingPayable()->toDecimal(),
                ];

                continue;
            }

            foreach ($purchase->sellerShares as $share) {
                $outstanding = $purchase->outstandingForSeller($share->seller_id);

                $rows[] = [
                    'property' => $purchase->property->reference,
                    'seller' => $share->seller->name,
                    'agreed' => $share->amount,
                    'paid' => Money::of($share->amount)->minus($outstanding)->toDecimal(),
                    'outstanding' => $outstanding->toDecimal(),
                ];
            }
        }

        return [
            'columns' => [
                ['key' => 'property', 'label' => 'Property'],
                ['key' => 'seller', 'label' => 'Seller'],
                ['key' => 'agreed', 'label' => 'Agreed amount', 'align' => 'money'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'money'],
                ['key' => 'outstanding', 'label' => 'Outstanding', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['agreed', 'paid', 'outstanding']),
        ];
    }

    private function buyerOverdue(array $filters): array
    {
        $installments = Installment::query()
            ->with(['sale.property', 'sale.buyer'])
            ->outstanding()
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->orderBy('due_date')
            ->get();

        $rows = $installments->map(fn (Installment $installment) => [
            'due_date' => $installment->due_date->toDateString(),
            'property' => $installment->sale->property->reference,
            'buyer' => $installment->sale->buyer->name,
            'no' => $installment->installment_no,
            'amount' => $installment->amount,
            'paid' => $installment->paid_amount,
            'remaining' => $installment->remaining()->toDecimal(),
            'status' => $installment->statusLabel(),
        ])->all();

        return [
            'columns' => [
                ['key' => 'due_date', 'label' => 'Due date'],
                ['key' => 'property', 'label' => 'Property'],
                ['key' => 'buyer', 'label' => 'Buyer'],
                ['key' => 'no', 'label' => 'No.'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'money'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'money'],
                ['key' => 'remaining', 'label' => 'Remaining', 'align' => 'money'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'totals' => $this->sumColumns($rows, ['amount', 'paid', 'remaining']),
        ];
    }

    private function dateWiseFinancial(array $filters): array
    {
        $scope = array_filter([
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ]);

        $summary = $this->ledger->summary($scope);

        $rows = [
            ['item' => 'Sale revenue recognised', 'amount' => $this->ledger->totalIncome($scope)->toDecimal()],
            ['item' => 'Cost of properties sold', 'amount' => '-'.ltrim($this->ledger->balance(\App\Support\LedgerAccounts::COST_OF_PROPERTIES_SOLD, $scope)->toDecimal(), '-')],
            ['item' => 'Expenses recognised', 'amount' => '-'.ltrim($this->ledger->totalExpenses($scope)->toDecimal(), '-')],
            ['item' => 'Net result', 'amount' => $this->ledger->netResult($scope)->toDecimal()],
            ['item' => 'Cash and bank balance (as at end)', 'amount' => $summary['cash']->toDecimal()],
            ['item' => 'Buyer receivables outstanding', 'amount' => $summary['buyer_receivable']->toDecimal()],
            ['item' => 'Seller payables outstanding', 'amount' => $summary['seller_payable']->toDecimal()],
            ['item' => 'Buyer advances held', 'amount' => $summary['buyer_advance']->toDecimal()],
            ['item' => 'Investor profit payable', 'amount' => $summary['investor_profit_payable']->toDecimal()],
            ['item' => 'Unsold property cost carried', 'amount' => $summary['property_cost']->toDecimal()],
        ];

        return [
            'columns' => [
                ['key' => 'item', 'label' => 'Item'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [],
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private function sumColumns(array $rows, array $keys): array
    {
        $totals = [];

        foreach ($keys as $key) {
            $totals[$key] = Money::sum(array_map(
                fn ($row) => $row[$key] ?? 0,
                $rows,
            ))->toDecimal();
        }

        return $totals;
    }
}
