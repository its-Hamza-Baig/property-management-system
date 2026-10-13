<?php

namespace App\Providers;

use App\Models\AccountTransfer;
use App\Models\ApprovalRequest;
use App\Models\Buyer;
use App\Models\BuyerPayment;
use App\Models\Document;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Investor;
use App\Models\InvestorDistribution;
use App\Models\Property;
use App\Models\PropertyInvestorAllocation;
use App\Models\PropertyPurchase;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\SellerPayment;
use App\Models\User;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\FinancialAccountPolicy;
use App\Policies\InvestorPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\PropertyPurchasePolicy;
use App\Policies\SalePolicy;
use App\Policies\SimpleTenantPolicy;
use App\Policies\StaffPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerQuery;
use App\Services\Tenancy\TenantContext;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        Investor::class => InvestorPolicy::class,
        Property::class => PropertyPolicy::class,
        PropertyPurchase::class => PropertyPurchasePolicy::class,
        Expense::class => ExpensePolicy::class,
        Sale::class => SalePolicy::class,
        FinancialAccount::class => FinancialAccountPolicy::class,
        Document::class => DocumentPolicy::class,
        ApprovalRequest::class => ApprovalRequestPolicy::class,
        User::class => StaffPolicy::class,
    ];

    /**
     * Models with no special rules beyond "same workspace, right permission".
     *
     * @var array<class-string, array{view:string,manage:string}>
     */
    private array $simplePolicies = [
        Seller::class => ['view' => Permissions::PURCHASES_VIEW, 'manage' => Permissions::PURCHASES_MANAGE],
        SellerPayment::class => ['view' => Permissions::PURCHASES_VIEW, 'manage' => Permissions::PAYMENTS_RECORD],
        Buyer::class => ['view' => Permissions::SALES_VIEW, 'manage' => Permissions::SALES_MANAGE],
        BuyerPayment::class => ['view' => Permissions::SALES_VIEW, 'manage' => Permissions::PAYMENTS_RECORD],
        Reservation::class => ['view' => Permissions::SALES_VIEW, 'manage' => Permissions::SALES_MANAGE],
        AccountTransfer::class => ['view' => Permissions::ACCOUNTS_VIEW, 'manage' => Permissions::ACCOUNTS_MANAGE],
        PropertyInvestorAllocation::class => ['view' => Permissions::ALLOCATIONS_VIEW, 'manage' => Permissions::ALLOCATIONS_MANAGE],
        InvestorDistribution::class => ['view' => Permissions::ALLOCATIONS_VIEW, 'manage' => Permissions::DISTRIBUTIONS_MANAGE],
    ];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(EntitlementManager::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(Ledger::class);
        $this->app->singleton(LedgerQuery::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::unguard(false);
        Paginator::useBootstrapFive();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerPolicies();
        $this->registerPermissionGates();
    }

    private function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->simplePolicies as $model => $permissions) {
            Gate::policy($model, SimpleTenantPolicy::class);
        }

        SimpleTenantPolicy::configure($this->simplePolicies);
    }

    /**
     * Every permission key becomes a gate, so a controller can write
     * $this->authorize('expenses.manage') for an action that is not tied to a
     * single model instance.
     *
     * A Super Admin deliberately receives nothing here: the platform role
     * carries no rights over customer business data.
     */
    private function registerPermissionGates(): void
    {
        foreach (Permissions::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        Gate::define('platform.manage', fn (User $user) => $user->isSuperAdmin());
    }
}
