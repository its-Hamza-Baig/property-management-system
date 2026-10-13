<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseFundingSource;
use App\Models\ExpensePayment;
use App\Models\FinancialAccount;
use App\Models\Investor;
use App\Models\Property;
use App\Models\Sale;
use App\Services\Approvals\ApprovalManager;
use App\Services\Expenses\ExpenseService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ApprovalManager $approvals,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()
            ->with(['category', 'property', 'sale.property'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->integer('property_id'), fn ($q, $id) => $q->where('property_id', $id))
            ->when($request->string('from')->toString(), fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->string('to')->toString(), fn ($q, $d) => $q->whereDate('expense_date', '<=', $d));

        $totals = (clone $query)->where('status', Expense::STATUS_CONFIRMED)->sum('amount');

        return view('expenses.index', [
            'expenses' => $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'confirmedTotal' => Money::of($totals),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'properties' => Property::orderBy('reference')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.form', [
            'expense' => new Expense(['expense_date' => now()->toDateString()]),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'properties' => Property::orderBy('reference')->get(),
            'sales' => Sale::with('property')->whereIn('status', [Sale::STATUS_DRAFT, Sale::STATUS_APPROVED, Sale::STATUS_FINALIZED])->get(),
            'investors' => Investor::where('status', Investor::STATUS_ACTIVE)->orderBy('name')->get(),
            'preset' => [
                'property_id' => $request->integer('property_id'),
                'sale_id' => $request->integer('sale_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $data = $this->validated($request);

        $expense = DB::transaction(function () use ($data, $request) {
            $category = ExpenseCategory::findOrFail($data['expense_category_id']);

            $expense = Expense::create([
                'property_id' => $data['property_id'] ?? null,
                'sale_id' => $data['sale_id'] ?? null,
                'expense_category_id' => $category->id,
                'expense_date' => $data['expense_date'],
                'amount' => Money::of($data['amount'])->toDecimal(),
                'description' => $data['description'] ?? null,
                'status' => Expense::STATUS_DRAFT,
                'is_capitalized' => $category->is_capitalized,
                'created_by' => $request->user()->id,
            ]);

            $this->syncFunding($expense, $data['funding'] ?? []);

            return $expense;
        });

        return redirect()->route('expenses.show', $expense)
            ->with('status', 'Draft expense saved. Confirm it to record the cost.');
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'property', 'sale.property', 'payments.financialAccount', 'fundingSources.investor', 'documents.currentVersion']);

        return view('expenses.show', [
            'expense' => $expense,
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'outstanding' => $expense->outstandingPayable(),
            'paid' => $expense->paidTotal(),
        ]);
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        $expense->load('fundingSources');

        return view('expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'properties' => Property::orderBy('reference')->get(),
            'sales' => Sale::with('property')->get(),
            'investors' => Investor::where('status', Investor::STATUS_ACTIVE)->orderBy('name')->get(),
            'preset' => [],
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $data = $this->validated($request);

        DB::transaction(function () use ($expense, $data) {
            $category = ExpenseCategory::findOrFail($data['expense_category_id']);

            $expense->update([
                'property_id' => $data['property_id'] ?? null,
                'sale_id' => $data['sale_id'] ?? null,
                'expense_category_id' => $category->id,
                'expense_date' => $data['expense_date'],
                'amount' => Money::of($data['amount'])->toDecimal(),
                'description' => $data['description'] ?? null,
                'is_capitalized' => $category->is_capitalized,
            ]);

            $this->syncFunding($expense, $data['funding'] ?? []);
        });

        return redirect()->route('expenses.show', $expense)->with('status', 'Draft expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('status', 'Draft expense removed.');
    }

    public function confirm(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('confirm', $expense);

        $amount = Money::of($expense->amount);

        if ($this->approvals->isRequired(ApprovalRule::ACTION_EXPENSE, $amount)) {
            try {
                $this->approvals->assertApproved(ApprovalRule::ACTION_EXPENSE, $expense, $amount);
            } catch (RuntimeException) {
                $this->approvals->request(ApprovalRule::ACTION_EXPENSE, $expense, $amount);
                $expense->update(['status' => Expense::STATUS_PENDING_APPROVAL]);

                return back()->with('status', 'This expense is above the approval threshold. A request has been sent and nothing has been posted yet.');
            }
        }

        try {
            $this->expenses->confirm($expense);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Expense confirmed and recorded.');
    }

    public function cancel(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('cancel', $expense);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->expenses->cancel($expense, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Expense cancelled with a reversing entry. The original record has been kept.');
    }

    public function storePayment(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('pay', $expense);

        $data = $request->validate([
            'financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->expenses->recordPayment($expense, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Payment recorded. It settles the payable and is not counted as a second expense.');
    }

    public function reversePayment(Request $request, ExpensePayment $payment): RedirectResponse
    {
        $this->authorize('pay', $payment->expense);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->expenses->reversePayment($payment, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Payment reversed with a linked entry.');
    }

    /**
     * Who the money belongs to, which is a different question from who
     * physically paid it.
     *
     * @param  list<array{funder_type?:string,investor_id?:int,amount?:string}>  $rows
     */
    private function syncFunding(Expense $expense, array $rows): void
    {
        $expense->fundingSources()->delete();

        foreach ($rows as $row) {
            if (empty($row['amount']) || (float) $row['amount'] <= 0) {
                continue;
            }

            ExpenseFundingSource::create([
                'expense_id' => $expense->id,
                'funder_type' => $row['funder_type'] ?? ExpenseFundingSource::FUNDER_ACCOUNT_HOLDER,
                'investor_id' => ($row['funder_type'] ?? null) === ExpenseFundingSource::FUNDER_INVESTOR
                    ? ($row['investor_id'] ?? null)
                    : null,
                'amount' => Money::of($row['amount'])->toDecimal(),
            ]);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'funding' => ['array'],
            'funding.*.funder_type' => ['nullable', 'in:account_holder,investor'],
            'funding.*.investor_id' => ['nullable', 'exists:investors,id'],
            'funding.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
