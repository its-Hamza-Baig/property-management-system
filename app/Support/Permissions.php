<?php

namespace App\Support;

/**
 * The catalogue of staff permissions.
 *
 * Account holders implicitly hold every permission in their own workspace.
 * Staff hold only what is stored in users.permissions. Every protected action
 * is checked on the backend through a Gate; hiding a menu item is never enough.
 */
class Permissions
{
    // Masters
    public const INVESTORS_VIEW = 'investors.view';
    public const INVESTORS_MANAGE = 'investors.manage';
    public const PROPERTIES_VIEW = 'properties.view';
    public const PROPERTIES_MANAGE = 'properties.manage';
    public const STAFF_MANAGE = 'staff.manage';

    // Purchases and expenses
    public const PURCHASES_VIEW = 'purchases.view';
    public const PURCHASES_MANAGE = 'purchases.manage';
    public const EXPENSES_VIEW = 'expenses.view';
    public const EXPENSES_MANAGE = 'expenses.manage';

    // Money
    public const ACCOUNTS_VIEW = 'accounts.view';
    public const ACCOUNTS_MANAGE = 'accounts.manage';
    public const PAYMENTS_RECORD = 'payments.record';
    public const PAYMENTS_REVERSE = 'payments.reverse';

    // Sales
    public const SALES_VIEW = 'sales.view';
    public const SALES_MANAGE = 'sales.manage';
    public const SALES_FINALIZE = 'sales.finalize';
    public const SALES_CANCEL = 'sales.cancel';

    // Investor capital
    public const ALLOCATIONS_VIEW = 'allocations.view';
    public const ALLOCATIONS_MANAGE = 'allocations.manage';
    public const DISTRIBUTIONS_MANAGE = 'distributions.manage';

    // Cross-cutting
    public const APPROVALS_DECIDE = 'approvals.decide';
    public const DOCUMENTS_VIEW = 'documents.view';
    public const DOCUMENTS_MANAGE = 'documents.manage';
    public const REPORTS_VIEW = 'reports.view';
    public const REPORTS_EXPORT = 'reports.export';
    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * Grouped for the staff permission form. Keys are display group names.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Investors' => [
                self::INVESTORS_VIEW => 'View investors',
                self::INVESTORS_MANAGE => 'Add and edit investors',
            ],
            'Properties' => [
                self::PROPERTIES_VIEW => 'View properties',
                self::PROPERTIES_MANAGE => 'Add and edit properties',
            ],
            'Purchases and expenses' => [
                self::PURCHASES_VIEW => 'View purchases',
                self::PURCHASES_MANAGE => 'Record purchases and seller payments',
                self::EXPENSES_VIEW => 'View expenses',
                self::EXPENSES_MANAGE => 'Record and pay expenses',
            ],
            'Cash and bank' => [
                self::ACCOUNTS_VIEW => 'View cash and bank accounts',
                self::ACCOUNTS_MANAGE => 'Add and edit accounts, make transfers',
                self::PAYMENTS_RECORD => 'Record receipts and payments',
                self::PAYMENTS_REVERSE => 'Reverse a confirmed payment',
            ],
            'Sales' => [
                self::SALES_VIEW => 'View reservations and sales',
                self::SALES_MANAGE => 'Create and edit reservations and sales',
                self::SALES_FINALIZE => 'Finalise a sale',
                self::SALES_CANCEL => 'Cancel a sale and handle refunds',
            ],
            'Investor capital' => [
                self::ALLOCATIONS_VIEW => 'View investor allocations',
                self::ALLOCATIONS_MANAGE => 'Manage allocations, transfers and exits',
                self::DISTRIBUTIONS_MANAGE => 'Pay investor profit distributions',
            ],
            'Approvals, documents and reports' => [
                self::APPROVALS_DECIDE => 'Approve or reject requests',
                self::DOCUMENTS_VIEW => 'View documents',
                self::DOCUMENTS_MANAGE => 'Upload and replace documents',
                self::REPORTS_VIEW => 'View reports',
                self::REPORTS_EXPORT => 'Export to Excel and PDF',
                self::SETTINGS_MANAGE => 'Manage workspace settings',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];
        foreach (self::groups() as $group) {
            foreach ($group as $key => $label) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The "Staff" preset: day to day recording, no finalising, approving,
     * reversing or settings.
     *
     * @return list<string>
     */
    public static function staffPreset(): array
    {
        return [
            self::INVESTORS_VIEW,
            self::PROPERTIES_VIEW,
            self::PURCHASES_VIEW,
            self::EXPENSES_VIEW,
            self::EXPENSES_MANAGE,
            self::ACCOUNTS_VIEW,
            self::PAYMENTS_RECORD,
            self::SALES_VIEW,
            self::ALLOCATIONS_VIEW,
            self::DOCUMENTS_VIEW,
            self::REPORTS_VIEW,
        ];
    }

    /**
     * The "Admin" preset: everything except workspace settings, which stays
     * with the account holder unless granted explicitly.
     *
     * @return list<string>
     */
    public static function adminPreset(): array
    {
        return array_values(array_diff(self::all(), [self::SETTINGS_MANAGE]));
    }
}
