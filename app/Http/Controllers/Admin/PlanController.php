<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Audit\AuditLogger;
use App\Support\Entitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount('tenants')->orderBy('name')->get(),
            'definitions' => Entitlements::definitions(),
        ]);
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', [
            'plan' => $plan,
            'definitions' => Entitlements::definitions(),
            'limits' => array_merge(Entitlements::defaults(), $plan->limits ?? []),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'limits' => ['array'],
        ]);

        $limits = [];

        foreach (Entitlements::definitions() as $key => $definition) {
            $limits[$key] = $definition['type'] === 'bool'
                ? $request->boolean('limits.'.$key)
                : ($request->input('limits.'.$key) ?? $definition['default']);
        }

        $before = $plan->limits;

        $plan->update([
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'is_active' => $request->boolean('is_active'),
            'limits' => $limits,
        ]);

        $this->audit->log(
            action: 'plan.updated',
            subject: $plan,
            description: 'Plan limits changed for '.$plan->name,
            oldValues: $before,
            newValues: $limits,
        );

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan saved. Accounts on this plan pick up the change immediately, and nothing is deleted.');
    }
}
