<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class FinancialAccountController extends Controller
{
    public function __construct(
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $accounts = FinancialAccount::with('ledgerAccount')->orderBy('type')->orderBy('name')->get();

        return view('accounts.index', [
            'accounts' => $accounts,
            'balances' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->currentBalance()]),
            'total' => Money::sum($accounts->map(fn ($a) => $a->currentBalance())),
            'transfers' => AccountTransfer::with('fromAccount', 'toAccount')->latest('id')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', FinancialAccount::class);

        return view('accounts.form', ['account' => new FinancialAccount(['type' => 'cash'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $account = $this->cash->openAccount($this->validated($request));

        $this->audit->log('account.created', $account, $account->name);

        return redirect()->route('accounts.index')->with('status', 'Account created.');
    }

    public function show(FinancialAccount $account): RedirectResponse
    {
        return redirect()->route('accounts.statement', $account);
    }

    public function edit(FinancialAccount $account): View
    {
        $this->authorize('update', $account);

        return view('accounts.form', ['account' => $account]);
    }

    /**
     * Only the descriptive fields can be edited. The opening balance was
     * posted to the ledger when the account was created, so changing it here
     * would silently restate history.
     */
    public function update(Request $request, FinancialAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $account->only(['name', 'bank_name', 'account_number', 'is_active']);
        $account->update($data + ['is_active' => $request->boolean('is_active')]);
        $account->ledgerAccount?->update(['name' => $account->name]);

        $this->audit->logChange('account.updated', $account, $before);

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    /** Every movement on one account, with a running balance. */
    public function statement(Request $request, FinancialAccount $account): View
    {
        $this->authorize('view', $account);

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $entries = LedgerEntry::query()
            ->where('ledger_account_id', $account->ledgerAccount?->id)
            ->with(['journal', 'property'])
            ->when($from, fn ($q) => $q->whereHas('journal', fn ($j) => $j->whereDate('journal_date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('journal', fn ($j) => $j->whereDate('journal_date', '<=', $to)))
            ->orderBy('id')
            ->get();

        return view('accounts.statement', [
            'account' => $account,
            'entries' => $entries,
            'balance' => $account->currentBalance(),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function createTransfer(): View
    {
        $this->authorize('create', FinancialAccount::class);

        return view('accounts.transfer', [
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $data = $request->validate([
            'from_financial_account_id' => ['required', 'exists:financial_accounts,id'],
            'to_financial_account_id' => ['required', 'exists:financial_accounts,id', 'different:from_financial_account_id'],
            'transfer_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->cash->transfer($data + ['idempotency_key' => $request->string('idempotency_key')->toString() ?: null]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('accounts.index')
            ->with('status', 'Transfer recorded. A transfer moves money between your own accounts and is not counted as income or expense.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['cash', 'bank'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_date' => ['required', 'date'],
        ]);
    }
}
