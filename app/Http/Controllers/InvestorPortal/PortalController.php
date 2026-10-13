<?php

namespace App\Http\Controllers\InvestorPortal;

use App\Http\Controllers\Controller;
use App\Models\InvestorProfitPayable;
use App\Models\PropertyInvestorAllocation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The optional investor portal.
 *
 * Read only, and scoped twice: to the workspace, and then to the one
 * investor record this login belongs to. An investor never sees another
 * investor, nor the overall books of the business.
 */
class PortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $investor = $this->investor($request);

        $allocations = PropertyInvestorAllocation::where('investor_id', $investor->id)
            ->active()
            ->with('property')
            ->get();

        $payables = InvestorProfitPayable::where('investor_id', $investor->id)->get();

        return view('portal.dashboard', [
            'investor' => $investor,
            'activeCount' => $allocations->count(),
            'totalCapital' => Money::sum($allocations->pluck('capital_amount')),
            'profitEarned' => Money::sum($payables->pluck('profit_amount')),
            'profitPaid' => Money::sum($payables->pluck('paid_amount')),
            'profitOutstanding' => $investor->outstandingProfitPayable(),
            'allocations' => $allocations,
        ]);
    }

    public function investments(Request $request): View
    {
        $investor = $this->investor($request);

        return view('portal.investments', [
            'investor' => $investor,
            'allocations' => PropertyInvestorAllocation::where('investor_id', $investor->id)
                ->with('property')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function statement(Request $request): View
    {
        $investor = $this->investor($request);

        return view('portal.statement', [
            'investor' => $investor,
            'payables' => InvestorProfitPayable::where('investor_id', $investor->id)
                ->with(['sale.property', 'distributions.financialAccount'])
                ->orderByDesc('id')
                ->get(),
            'profitOutstanding' => $investor->outstandingProfitPayable(),
        ]);
    }

    private function investor(Request $request)
    {
        $investor = $request->user()->investor;

        abort_if($investor === null, 403, 'This login is not linked to an investor record.');
        abort_unless($investor->portal_enabled, 403, 'Portal access is switched off for your record.');

        return $investor;
    }
}
