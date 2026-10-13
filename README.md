# Property Management System

A multi-tenant property business application: purchases, sales, investors,
expenses, payments, cash and bank accounts, profit and loss, staff and
financial reports. Built with Laravel 13, PHP 8.3, MySQL 8 and Bootstrap 5.
All amounts are in PKR and the whole interface is in English.

---

## What it does

| Area | What you get |
|---|---|
| **Workspaces** | Every account holder gets an isolated workspace. No account can see another one. |
| **Properties** | Available → Reserved → Under sale → Sold. Sold stock stops counting against your plan limit but keeps its whole history. |
| **Purchases** | Purchase price, extra expenses, multiple sellers, partial payments, outstanding payable. |
| **Expenses** | Separates the expense *incurred* from the amount *paid*, and records both who paid and whose capital it was. |
| **Sales** | Reservations, tokens, installment plans, receipts, sale expenses, finalisation, cancellation, audited corrections. |
| **Investors** | Capital allocation with a cap, separate ownership and profit-sharing ratios, transfers and exits, profit payable and actual distributions. |
| **Cash and bank** | Multiple accounts, receipts, payments and transfers between your own accounts. |
| **Approvals** | Optional, per action, with an amount threshold. Nobody approves their own request. |
| **Documents** | Private storage with per-plan quotas, versioning and authorised downloads. |
| **Reports** | Twelve reports, each with date filters and Excel/CSV and PDF export. |
| **Platform admin** | Account activation, plans and limits, backups, and reasoned break-glass support access. |

---

## Getting it running on your own machine

You need **PHP 8.2+**, **Composer**, and **MySQL 8** (or MariaDB 10.6+).

### 1. Install the PHP packages

```bash
composer install
```

### 2. Create the configuration file

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and point it at your database:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pms
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 3. Create the database

In MySQL:

```sql
CREATE DATABASE pms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Build the tables and the starting data

```bash
php artisan migrate
php artisan db:seed
```

The seeder creates three plans (Free, Pilot, Unlimited) and one platform
administrator. It prints the login on screen:

```
admin@example.com / password
```

**Change that password the first time you sign in.**

To create another administrator later:

```bash
php artisan pms:create-super-admin
```

### 5. Start it

```bash
php artisan serve
```

Open <http://localhost:8000>.

---

## Your first run through the system

This is the quickest path from an empty system to a finished sale with the
profit split between investors.

1. **Sign in as the administrator** at `/login` and open **Customer accounts**.
2. **Register a business**: sign out, go to `/register`, fill the form, confirm
   the email (in local development the email is written to
   `storage/logs/laravel.log` — open the link from there).
3. **Activate it**: sign back in as the administrator, open the request under
   **Customer accounts**, pick a plan and press **Activate**. This creates the
   chart of accounts, the starter expense categories and the approval rules.
4. **Sign in as the account holder.** Now, inside the workspace:
   - **Cash and bank → Add account.** Nothing can be paid or received until
     there is at least one account.
   - **Properties → Add property.**
   - **Purchases → Record purchase**, then press **Confirm purchase**. This is
     the moment the price becomes recorded cost.
   - **Investors → Add investor**, then on the property open **Investors** and
     allocate their capital.
   - **Sales → Create sale**, set the installment plan, record the token and
     receipts, add any sale expenses, then **Finalise sale**.
   - Open the sale again to see each investor's profit share, and pay them.
   - **Reports** for the twelve statements, with Excel and PDF export.

---

## How the money works

The thing that makes this reliable is that **every financial fact is a balanced
double-entry journal** written by one service (`App\Services\Ledger\Ledger`).
Nothing anywhere else writes money. Each concept the business needs to keep
apart has its own account, so the rules below are arithmetic rather than
discipline:

| Rule | Why it holds |
|---|---|
| Purchase cost is counted once | Confirming a purchase debits Property Cost and credits Seller Payable. Paying a seller debits Seller Payable and credits cash — it never touches Property Cost again. |
| An expense is not counted twice | Confirming debits the expense (or Property Cost, if capitalised) and credits Expense Payable. Paying it clears the payable. |
| A transfer is not income or expense | It debits one cash account and credits another, touching no income or expense account at all. |
| A token is not profit | It credits Buyer Advance, which is a liability, until the sale is finalised. |
| Profit is not cash | Finalisation creates a Buyer Receivable. Cash arrives separately against that receivable, and the two are reported as different numbers. |
| An investor payout is not an expense | It debits Investor Profit Payable, never an expense account. |
| Nothing is overwritten | Reversals and corrections are new balanced journals that point back at the original, which stays exactly where it was. |

Money is held as integer paisa in `App\Support\Money` and stored in
`DECIMAL(18,2)` columns. No float ever touches a calculation, and all rounding
happens in one place. When profit is split between investors the remainder is
distributed largest-share-first, so the parts always add back to exactly the
total.

`docs/BUSINESS-RULES.md` works through the journals event by event.

---

## Running the tests

The suite needs its own database, and its own environment file:

```sql
CREATE DATABASE pms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
cp .env.testing.example .env.testing   # then fill in your database details
php artisan test
```

`.env.testing` is not committed, so no database password lives in the
repository. `phpunit.xml` pins only the database *name*, which means a stray
run can never point the suite at your live data — and `RefreshDatabase` drops
every table it touches.

It runs against real MySQL rather than SQLite on purpose, because the money
columns are `DECIMAL` and the financial tests are only meaningful on the engine
the application actually ships on.

What is covered:

| File | What it proves |
|---|---|
| `FinancialIntegrityTest` | Every double-counting rule above, plus reversals, cancellations, corrections, concurrent duplicate payments, and that the books always balance. |
| `TenantIsolationTest` | One workspace cannot read or change another through pages, ids, downloads, exports or reports; staff permissions; deactivated logins; the investor portal; and that a Super Admin has no standing access. |
| `EntitlementsAndApprovalsTest` | Plan limits, that a downgrade deletes nothing, approval thresholds, and that nobody approves their own request. |
| `SmokeTest` | Every screen renders, and all twelve reports export to CSV and PDF. |

---

## Scheduled work

One cron entry drives everything (see `routes/console.php`):

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

- `pms:backup` runs nightly, prunes old backups and alerts the administrators
  if it fails.
- `pms:send-reminders` sends installment due and overdue notices. Each one is
  logged, so the same reminder is never sent twice.

Both can be run by hand:

```bash
php artisan pms:backup
php artisan pms:send-reminders
```

---

## Deployment

See **`docs/DEPLOYMENT.md`** for hosting requirements, the step-by-step upload
and setup, the cron entry, and the backup *and restore* procedure.

---

## Where things live

```
app/
  Http/Controllers/     one per module, plus Admin/ and InvestorPortal/
  Http/Middleware/      ResolveTenant, EnsureAccountIsUsable, EnsureRole
  Models/               Eloquent models; tenant-owned ones use BelongsToTenant
  Policies/             authorisation, checked on every action
  Services/
    Ledger/             the only code that writes money
    Purchases/ Expenses/ Sales/ Investors/ Cash/   business operations
    Approvals/ Documents/ Reports/ Backup/ Tenancy/ Entitlements/ Audit/
  Support/              Money, Permissions, Entitlements, LedgerAccounts
database/migrations/    38 migrations
resources/views/        Blade + Bootstrap 5
tests/Feature/          the four suites above
docs/                   the original specification, plus deployment notes
```
