<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Installment;
use App\Models\InvestorProfitPayable;
use App\Models\Property;
use App\Models\Sale;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Ledger\LedgerQuery;
use App\Support\Entitlements;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(LedgerQuery $ledger, EntitlementManager $entitlements): View
    {
        $counts = Property::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeProperties = collect(Property::ACTIVE_STATUSES)
            ->sum(fn (string $status) => (int) ($counts[$status] ?? 0));

        // Realised profit on closed deals, which is a different number from
        // the cash in the accounts and is labelled as such on the page.
        $realizedProfit = Money::of(Sale::finalized()->sum('realized_profit'));

        $investorPayable = Money::of(InvestorProfitPayable::sum('profit_amount'))
            ->minus(Money::of(InvestorProfitPayable::sum('paid_amount')));

        $overdue = Installment::overdue()->with('sale.property', 'sale.buyer')->orderBy('due_date')->limit(8)->get();

        $upcoming = Installment::outstanding()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->with('sale.property', 'sale.buyer')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'summary' => $ledger->summary(),
            'realizedProfit' => $realizedProfit,
            'investorPayable' => $investorPayable,
            'counts' => $counts,
            'activeProperties' => $activeProperties,
            'propertyLimit' => $entitlements->value(Entitlements::MAX_PROPERTIES),
            'overdueInstallments' => $overdue,
            'upcomingInstallments' => $upcoming,
            'overdueTotal' => Money::sum(Installment::overdue()->get()->map(fn ($i) => $i->remaining())),
            'pendingApprovals' => ApprovalRequest::pending()->with('requester')->latest()->limit(5)->get(),
            'recentSales' => Sale::with('property', 'buyer')->latest('id')->limit(5)->get(),
        ]);
    }
}
