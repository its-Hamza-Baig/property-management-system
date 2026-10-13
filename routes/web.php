<?php

use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlatformDashboardController;
use App\Http\Controllers\Admin\SupportAccessController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\InvestorPortal\PortalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));

    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get('forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendLink'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'reset'])->name('password.update');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email verification and account status
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verify/resend', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Shown while a workspace is awaiting approval, suspended or closed.
    Route::get('account-status', [RegisterController::class, 'status'])->name('account.status');
});

/*
|--------------------------------------------------------------------------
| Super Admin: the platform, never the customer books
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'account.usable', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');

        Route::get('accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/{tenant}', [AdminAccountController::class, 'show'])->name('accounts.show');
        Route::post('accounts/{tenant}/activate', [AdminAccountController::class, 'activate'])->name('accounts.activate');
        Route::post('accounts/{tenant}/reject', [AdminAccountController::class, 'reject'])->name('accounts.reject');
        Route::post('accounts/{tenant}/suspend', [AdminAccountController::class, 'suspend'])->name('accounts.suspend');
        Route::post('accounts/{tenant}/reactivate', [AdminAccountController::class, 'reactivate'])->name('accounts.reactivate');
        Route::put('accounts/{tenant}/entitlements', [AdminAccountController::class, 'updateEntitlements'])->name('accounts.entitlements');

        Route::get('plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
        Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

        // Reasoned, time-boxed, fully logged access to a customer workspace.
        Route::post('support-access/{tenant}', [SupportAccessController::class, 'store'])->name('support.store');
        Route::delete('support-access', [SupportAccessController::class, 'destroy'])->name('support.destroy');

        Route::get('backups', [AdminBackupController::class, 'index'])->name('backups.index');
        Route::post('backups/run', [AdminBackupController::class, 'run'])->name('backups.run');
    });

/*
|--------------------------------------------------------------------------
| Investor portal: read only, own data only
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'account.usable', 'role:investor'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('investments', [PortalController::class, 'investments'])->name('investments');
        Route::get('statement', [PortalController::class, 'statement'])->name('statement');
    });

/*
|--------------------------------------------------------------------------
| The workspace
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'account.usable', 'role:account_holder,staff'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('investors', InvestorController::class)->except('destroy');
    Route::post('investors/{investor}/status', [InvestorController::class, 'setStatus'])->name('investors.status');
    Route::post('investors/{investor}/portal', [InvestorController::class, 'setPortalAccess'])->name('investors.portal');
    Route::post('investors/{investor}/capital', [InvestorController::class, 'recordCapital'])->name('investors.capital');
    Route::get('investors/{investor}/statement', [InvestorController::class, 'statement'])->name('investors.statement');

    Route::resource('properties', PropertyController::class);
    Route::get('properties/{property}/ledger', [PropertyController::class, 'ledger'])->name('properties.ledger');
    Route::get('properties/{property}/allocations', [PropertyController::class, 'allocations'])->name('properties.allocations');
    Route::put('properties/{property}/allocations', [PropertyController::class, 'updateAllocations'])->name('properties.allocations.update');
    Route::post('properties/{property}/transfer-share', [PropertyController::class, 'transferShare'])->name('properties.transfer-share');

    Route::resource('sellers', SellerController::class)->except('show', 'destroy');
    Route::resource('buyers', BuyerController::class)->except('show', 'destroy');

    Route::resource('purchases', PurchaseController::class)->except('destroy');
    Route::post('purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
    Route::post('purchases/{purchase}/payments', [PurchaseController::class, 'storePayment'])->name('purchases.payments.store');
    Route::post('seller-payments/{payment}/reverse', [PurchaseController::class, 'reversePayment'])->name('seller-payments.reverse');

    Route::resource('expenses', ExpenseController::class);
    Route::post('expenses/{expense}/confirm', [ExpenseController::class, 'confirm'])->name('expenses.confirm');
    Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
    Route::post('expenses/{expense}/payments', [ExpenseController::class, 'storePayment'])->name('expenses.payments.store');
    Route::post('expense-payments/{payment}/reverse', [ExpenseController::class, 'reversePayment'])->name('expense-payments.reverse');

    Route::resource('accounts', FinancialAccountController::class)->except('destroy');
    Route::get('accounts/{account}/statement', [FinancialAccountController::class, 'statement'])->name('accounts.statement');
    Route::get('transfers/create', [FinancialAccountController::class, 'createTransfer'])->name('transfers.create');
    Route::post('transfers', [FinancialAccountController::class, 'storeTransfer'])->name('transfers.store');

    Route::resource('reservations', ReservationController::class)->except('destroy');
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('reservations/{reservation}/convert', [ReservationController::class, 'convert'])->name('reservations.convert');

    Route::resource('sales', SaleController::class);
    Route::post('sales/{sale}/finalize', [SaleController::class, 'finalize'])->name('sales.finalize');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::get('sales/{sale}/correct', [SaleController::class, 'correctForm'])->name('sales.correct');
    Route::post('sales/{sale}/correct', [SaleController::class, 'correct'])->name('sales.correct.store');
    Route::put('sales/{sale}/schedule', [SaleController::class, 'updateSchedule'])->name('sales.schedule');
    Route::post('sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');
    Route::post('buyer-payments/{payment}/reverse', [SaleController::class, 'reversePayment'])->name('buyer-payments.reverse');
    Route::post('profit-payables/{payable}/pay', [SaleController::class, 'payInvestor'])->name('profit-payables.pay');

    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    Route::resource('staff', StaffController::class)->except('destroy');
    Route::post('staff/{staff}/status', [StaffController::class, 'setStatus'])->name('staff.status');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/export/{format}', [ReportController::class, 'export'])
        ->whereIn('format', ['csv', 'pdf'])->name('reports.export');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/approvals', [SettingsController::class, 'updateApprovals'])->name('settings.approvals');
    Route::post('settings/categories', [SettingsController::class, 'storeCategory'])->name('settings.categories.store');
    Route::put('settings/categories/{category}', [SettingsController::class, 'updateCategory'])->name('settings.categories.update');
    Route::get('settings/audit-log', [SettingsController::class, 'auditLog'])->name('settings.audit');
});

/*
|--------------------------------------------------------------------------
| Shared by every signed-in user
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'account.usable'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Documents hang off many record types, so they share one controller.
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
