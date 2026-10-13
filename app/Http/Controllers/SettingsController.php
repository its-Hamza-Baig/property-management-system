<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRule;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Ledger\Ledger;
use App\Support\Entitlements;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Ledger $ledger,
        private readonly EntitlementManager $entitlements,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize(Permissions::SETTINGS_MANAGE);

        return view('settings.index', [
            'rules' => ApprovalRule::orderBy('action')->get(),
            'categories' => ExpenseCategory::orderBy('applies_to')->orderBy('name')->get(),
            'entitlements' => $this->entitlements->for(),
            'definitions' => Entitlements::definitions(),
            'tenant' => $request->user()->tenant,
            'propertiesUsed' => $this->entitlements->propertiesUsed(),
            'staffUsed' => $this->entitlements->staffUsed(),
        ]);
    }

    /**
     * Turn approval on or off per action, with an optional threshold so only
     * larger amounts are held up.
     */
    public function updateApprovals(Request $request): RedirectResponse
    {
        $this->authorize(Permissions::SETTINGS_MANAGE);

        $data = $request->validate([
            'rules' => ['array'],
            'rules.*.is_enabled' => ['nullable', 'boolean'],
            'rules.*.threshold_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($data['rules'] ?? [] as $action => $values) {
            $rule = ApprovalRule::where('action', $action)->first();

            if (! $rule) {
                continue;
            }

            $rule->update([
                'is_enabled' => (bool) ($values['is_enabled'] ?? false),
                'threshold_amount' => filled($values['threshold_amount'] ?? null)
                    ? Money::of($values['threshold_amount'])->toDecimal()
                    : null,
            ]);
        }

        $this->audit->log('settings.approvals_updated', null, 'Approval rules changed');

        return back()->with('status', 'Approval rules saved.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize(Permissions::SETTINGS_MANAGE);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['purchase', 'property', 'sale', 'general'])],
            'is_capitalized' => ['nullable', 'boolean'],
        ]);

        $category = ExpenseCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'applies_to' => $data['applies_to'],
            'is_capitalized' => $request->boolean('is_capitalized'),
            'is_active' => true,
        ]);

        // Every category gets its own ledger account so expense reporting by
        // category needs no extra bookkeeping.
        $this->ledger->createAccountForCategory($category);

        return back()->with('status', 'Expense category added.');
    }

    /**
     * The capitalised flag is frozen onto each expense when it is confirmed,
     * so editing a category here never restates reported profit.
     */
    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize(Permissions::SETTINGS_MANAGE);

        abort_unless($category->tenant_id === $request->user()->tenant_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_capitalized' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'is_capitalized' => $request->boolean('is_capitalized'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $category->ledgerAccount?->update(['name' => $category->name]);

        return back()->with('status', 'Category updated. Expenses already confirmed keep the treatment they were recorded with.');
    }

    public function auditLog(Request $request): View
    {
        $this->authorize(Permissions::SETTINGS_MANAGE);

        return view('settings.audit', [
            'logs' => AuditLog::query()
                ->with('user')
                ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->when($request->string('from')->toString(), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->string('to')->toString(), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->orderByDesc('id')
                ->paginate(40)
                ->withQueryString(),
        ]);
    }
}
