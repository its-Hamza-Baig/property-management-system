<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

/**
 * The platform view.
 *
 * Deliberately limited to operational counts. It shows how many properties a
 * workspace holds, never what they are worth, because the Super Admin has no
 * standing right to customer financial data.
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', [
            'byStatus' => $byStatus,
            'totalAccounts' => Tenant::count(),
            'pendingAccounts' => Tenant::where('status', Tenant::STATUS_PENDING_APPROVAL)
                ->latest('id')->limit(10)->get(),
            'totalUsers' => User::whereNotNull('tenant_id')->count(),
            'totalProperties' => Property::withoutTenantScope()->count(),
            'lastBackup' => BackupRun::latest('id')->first(),
            'failedBackups' => BackupRun::where('status', BackupRun::STATUS_FAILED)
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }
}
