<div align="center">

# Property Management System

**A multi-tenant SaaS for property trading businesses — purchases, sales, investors, expenses, cash and profit distribution.**

[![tests](https://github.com/its-Hamza-Baig/property-management-system/actions/workflows/tests.yml/badge.svg)](https://github.com/its-Hamza-Baig/property-management-system/actions/workflows/tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![Tests](https://img.shields.io/badge/tests-63%20passing-success)](#9-running-the-tests)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)

</div>

---

## Contents

1. [What this is](#1-what-this-is)
2. [What is built so far](#2-what-is-built-so-far)
3. [How the system works](#3-how-the-system-works)
4. [How the money works](#4-how-the-money-works-the-important-part)
5. [Who can do what](#5-who-can-do-what)
6. [Setup, command by command](#6-setup-command-by-command)
7. [Loading the demo data](#7-loading-the-demo-data)
8. [Your first walkthrough](#8-your-first-walkthrough)
9. [Running the tests](#9-running-the-tests)
10. [Features, with worked examples](#10-features-with-worked-examples)
11. [Scheduled jobs and commands](#11-scheduled-jobs-and-commands)
12. [Project layout](#12-project-layout)
13. [Deployment](#13-deployment)
14. [Not built yet](#14-not-built-yet)
15. [Licence](#15-licence)

---

## 1. What this is

A web application for a business that **buys property, holds it, and sells it
for a profit** — often with money put in by outside investors who each take an
agreed share of the result.

It answers the questions that business actually has:

- What do we own, and what did it really cost us?
- Who owes us money, and who do we owe?
- How much cash is actually in hand, as opposed to profit on paper?
- When this plot sells, who gets what — and how much have we already paid them?

It is **multi-tenant**: many separate businesses use one installation, and no
business can see another one's data. It is **not** an accounting package, a
banking app, or an ERP, and deliberately avoids becoming one.

| | |
|---|---|
| **Stack** | Laravel 13, PHP 8.3, MySQL 8, Blade + Bootstrap 5, minimal JavaScript |
| **Currency** | PKR only |
| **Language** | English only |
| **Scale** | ~284 files, 39 migrations, 37 models, 28 controllers, 20 services, 64 views |

---

## 2. What is built so far

Everything in this table is implemented and covered by tests.

| # | Area | Status |
|---|---|---|
| 1 | Multi-tenant workspaces with three layers of isolation | ✅ |
| 2 | Signup → email verification → admin activation | ✅ |
| 3 | Super Admin: accounts, plans, per-account limits, backups | ✅ |
| 4 | Break-glass support access (read-only, time-boxed, logged) | ✅ |
| 5 | Staff accounts with 25 granular permissions and two presets | ✅ |
| 6 | Investors — never deleted, deactivation blocked while invested | ✅ |
| 7 | Properties with the full Available → Reserved → Under sale → Sold lifecycle | ✅ |
| 8 | Purchases, multiple sellers, seller-wise outstanding balances | ✅ |
| 9 | Expenses, capitalised vs period, funding source separate from payer | ✅ |
| 10 | Multiple cash and bank accounts, with transfers | ✅ |
| 11 | Reservations with tokens held as a liability | ✅ |
| 12 | Sales, installment plans, receipts, overdue tracking | ✅ |
| 13 | Sale finalisation with frozen cost and realised profit | ✅ |
| 14 | Cancellation with forfeiture and refunds, history preserved | ✅ |
| 15 | Audited corrections to finalised sales | ✅ |
| 16 | Investor allocation cap, separate ownership and profit ratios | ✅ |
| 17 | Investor share transfers and exits | ✅ |
| 18 | Profit payable vs profit actually paid, tracked separately | ✅ |
| 19 | Full double-entry ledger underneath everything | ✅ |
| 20 | Configurable approval workflow with thresholds | ✅ |
| 21 | Private document storage with per-plan quotas and versioning | ✅ |
| 22 | In-app and email notifications with per-user preferences | ✅ |
| 23 | Due-date and overdue reminders, never sent twice | ✅ |
| 24 | Dashboard and **twelve** reports, all with CSV and PDF export | ✅ |
| 25 | Optional read-only investor portal | ✅ |
| 26 | Audit log of every correction, approval, export and download | ✅ |
| 27 | Daily backups with a **tested** restore procedure | ✅ |
| 28 | 63 automated tests, 314 assertions, against real MySQL | ✅ |

See [section 14](#14-not-built-yet) for what is deliberately **not** done yet.

---

## 3. How the system works

### The three kinds of login

```
┌──────────────────────────────────────────────────────────────┐
│  SUPER ADMIN  — runs the platform, sees no customer money    │
│     approves signups · sets plans and limits · backups       │
└───────────────────────────┬──────────────────────────────────┘
                            │ activates
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  WORKSPACE  "Shaheen Estates"        (one per business)      │
│                                                              │
│   ACCOUNT HOLDER ──── creates ───▶ STAFF (granular rights)   │
│          │                                                   │
│          ├── Properties ── Purchases ── Expenses             │
│          ├── Reservations ── Sales ── Installments           │
│          ├── Investors ── Allocations ── Distributions       │
│          └── Cash & bank accounts                            │
│                            │                                 │
│                            ▼                                 │
│                   ┌─────────────────┐                        │
│                   │  THE LEDGER     │  ← every figure        │
│                   │  (double entry) │    is read from here   │
│                   └─────────────────┘                        │
└───────────────────────────┬──────────────────────────────────┘
                            │ optional, read-only
                            ▼
┌──────────────────────────────────────────────────────────────┐
│  INVESTOR PORTAL — sees only their own holdings & statement  │
└──────────────────────────────────────────────────────────────┘
```

### The life of a property, step by step

```
 1. ADD PROPERTY              status: Available
         ↓
 2. RECORD PURCHASE  (draft)  nothing posted to the books yet
         ↓
 3. CONFIRM PURCHASE          ← cost recognised ONCE, seller payable created
         ↓
 4. ADD EXPENSES              capitalised ones raise the cost base
         ↓
 5. ALLOCATE INVESTORS        capital capped at the cost base
         ↓
 6. RESERVE  (optional)       status: Reserved, token held as a LIABILITY
         ↓
 7. CREATE SALE  (draft)      status: Under sale
         ↓
 8. INSTALLMENT PLAN          a plan only — moves no money by itself
         ↓
 9. RECEIVE PAYMENTS          cash in, recorded against what is owed
         ↓
10. FINALISE SALE             status: Sold
                              ← profit calculated, cost FROZEN onto the sale
                              ← each investor's share becomes a payable
         ↓
11. PAY INVESTORS             settles the payable — never an expense
```

At every step the three numbers that people usually confuse stay separate:

| | |
|---|---|
| **Sale value** | what was agreed |
| **Cash received** | what actually arrived in the bank |
| **Realised profit** | what was earned after cost and sale expenses |

---

## 4. How the money works (the important part)

This is what makes the system trustworthy, so it is worth two minutes.

**Every financial fact is a balanced double-entry journal**, written by exactly
one place in the codebase (`App\Services\Ledger\Ledger`). Nothing else writes
money. The service refuses any journal whose debits and credits do not match,
and writes inside a database transaction, so a half-posted entry cannot exist.

Because **each concept the business must keep apart has its own account**, the
"don't count it twice" rules stop being things a developer must remember and
become things the structure makes impossible:

| The rule | Why it cannot be broken |
|---|---|
| Purchase cost counted **once** | Confirming debits *Property Cost* and credits *Seller Payable*. Paying a seller debits *Seller Payable* and credits cash — it never touches *Property Cost* again. Ten installments still leave cost at the purchase price. |
| Expense ≠ expense payment | Confirming debits the expense and credits *Expense Payable*. Paying it clears the payable. A PKR 50,000 expense that gets paid is still PKR 50,000, not 100,000. |
| A transfer is **not** income | It debits one cash account and credits another, touching no income or expense account at all. Moving cash to the bank cannot read as a second sale. |
| A token is **not** profit | It credits *Buyer Advance*, a liability, until the sale is finalised. |
| Profit is **not** cash | Finalisation creates a *Buyer Receivable*. Cash arrives separately against it. Both are reported, labelled differently. |
| An investor payout is **not** an expense | It debits *Investor Profit Payable*. A distribution settles a liability; it is not a cost of the property. |
| Nothing is overwritten | Reversals and corrections are **new** balanced journals pointing back at the original, which stays untouched. |

### Precision

Money is held as **integer paisa** in `App\Support\Money` and stored in
`DECIMAL(18,2)`. No float ever touches a calculation, and all rounding happens
in that one class. When profit is split, the rounding remainder goes to the
largest shares first, so the parts always add back to exactly the total — never
one paisa out.

### Proof

```bash
php artisan test --filter=FinancialIntegrityTest
```

21 tests, one per rule above, each ending with a trial-balance check that
`SUM(debit) − SUM(credit) = 0`.

A full walkthrough of every journal is in [`docs/BUSINESS-RULES.md`](docs/BUSINESS-RULES.md).

---

## 5. Who can do what

| Role | Can | Cannot |
|---|---|---|
| **Super Admin** | Approve/suspend accounts, set plans and limits, view backups | See any customer property, money or report |
| **Account Holder** | Everything inside their own workspace | Touch another workspace; permanently delete investors or financial history |
| **Staff (Admin preset)** | Everything except workspace settings | Change approval rules or expense categories |
| **Staff (Staff preset)** | Record expenses and receipts, view most things | Finalise sales, approve, reverse payments, manage staff |
| **Investor (portal)** | See their own holdings and statement | See other investors, or anything in the back office |

**Super Admin access is genuinely restricted**, not just hidden. To look inside
a workspace they must start a support session with a written reason. It is
read-only, expires automatically, and **every page they open is written to that
customer's own audit log**, where the account holder can see it.

Staff permissions are checked on the **server for every action** — including
file downloads, report exports and dropdown endpoints — not merely hidden from
the menu.

---

## 6. Setup, command by command

### 6.1 What you need first

| | Minimum | Check with |
|---|---|---|
| PHP | 8.2+ with `mbstring`, `pdo_mysql`, `xml`, `curl`, `fileinfo`, `openssl` | `php -v` and `php -m` |
| Composer | 2.x | `composer --version` |
| MySQL | 8.0 (or MariaDB 10.6+) | `mysql --version` |
| Git | any | `git --version` |

`zip` and `bcmath` are **not** required.

### 6.2 Get the code

```bash
git clone git@github.com:its-Hamza-Baig/property-management-system.git
cd property-management-system
```

### 6.3 Install the PHP packages

```bash
composer install
```

> Takes a few minutes the first time. It downloads Laravel and about 100 other
> packages into `vendor/`, which is not stored in the repository.

### 6.4 Create your configuration file

```bash
cp .env.example .env
php artisan key:generate
```

`key:generate` writes a unique `APP_KEY` into `.env`. Laravel uses it to
encrypt sessions and cookies. **Never commit this file** — it is already
listed in `.gitignore`.

### 6.5 Create the databases

You need two: one for the application, one for the test suite.

```bash
mysql -u root -p -e "CREATE DATABASE pms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE pms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then open `.env` and fill in your connection details:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pms
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

### 6.6 Set up the test environment

```bash
cp .env.testing.example .env.testing
```

Open `.env.testing` and put in the same credentials, keeping
`DB_DATABASE=pms_testing`. Then:

```bash
php artisan key:generate --env=testing
```

> `.env.testing` is **not** committed, so no database password ever lives in
> the repository. `phpunit.xml` pins only the database *name*, which means a
> stray test run can never point at your live data.

### 6.7 Build the tables

```bash
php artisan migrate
```

Creates all 39 tables. You should see a list ending in
`add_credited_to_to_buyer_payments_table`.

### 6.8 Create the plans and the first administrator

```bash
php artisan db:seed
```

This creates three plans (Free, Pilot, Unlimited) and prints your first login:

```
Super Admin created: admin@example.com / password
```

**Change that password the moment you sign in.** To make your own instead:

```bash
php artisan pms:create-super-admin
```

### 6.9 Start the application

```bash
php artisan serve
```

Open **<http://localhost:8000>**.

### 6.10 The whole thing, in one block

```bash
git clone git@github.com:its-Hamza-Baig/property-management-system.git
cd property-management-system
composer install
cp .env.example .env
cp .env.testing.example .env.testing
php artisan key:generate
php artisan key:generate --env=testing
# → now edit .env and .env.testing with your database details
mysql -u root -p -e "CREATE DATABASE pms CHARACTER SET utf8mb4; CREATE DATABASE pms_testing CHARACTER SET utf8mb4;"
php artisan migrate
php artisan db:seed
php artisan serve
```

---

## 7. Loading the demo data

Rather than starting from an empty screen, load a complete worked example:

```bash
php artisan db:seed --class=DemoSeeder
```

This builds **"Shaheen Estates"** — an activated workspace with 8 properties
covering every possible state, 3 investors, 3 sellers, 4 buyers, two bank
accounts, real expenses, a cancelled-then-resold property and a purchase
waiting for approval.

It prints a summary, which should always end in a zero difference:

```
+--------------------------+---------------+
| Cash and bank            | 31,234,000.00 |
| Unsold property cost     | 20,950,000.00 |
| Buyer receivables        |  2,500,000.00 |
| Seller payables          |  4,500,000.00 |
| Buyer advances held      |  3,750,000.00 |
| Investor capital         | 11,400,000.00 |
| Investor profit payable  |    244,000.00 |
| Realised profit on sales |  2,785,000.00 |
| Trial balance difference |          0.00 |   ← must always be zero
+--------------------------+---------------+
```

### Demo logins — password is `password` for all

| Email | Role | What it shows |
|---|---|---|
| `owner@demo.test` | Account holder | Everything |
| `manager@demo.test` | Staff, admin preset | Everything except settings |
| `staff@demo.test` | Staff, limited preset | Recording only — no finalising or approving |
| `investor@demo.test` | Investor portal | Only their own two holdings |
| `admin@example.com` | Super Admin | Platform only, no customer money |

### What each demo property demonstrates

| Ref | Demonstrates |
|---|---|
| `SHE-001` | In stock; an investor funds exactly half the cost base |
| `SHE-002` | Under sale, 4 installments, the first **overdue and part-paid** |
| `SHE-003` | Sold; ownership 84% but profit share 70% — deliberately different |
| `SHE-004` | Sold outright, no investors, paid in full before finalisation |
| `SHE-005` | Reserved, PKR 500,000 token held as a liability |
| `SHE-006` | Two sellers on one deal, paid unevenly |
| `SHE-007` | Sale **cancelled** with forfeiture and refund, then **resold** as a new record |
| `SHE-008` | PKR 12.5m purchase sitting in the **approval queue** |

### Resetting the demo

```bash
php artisan migrate:fresh --force
php artisan db:seed --force
php artisan db:seed --class=DemoSeeder --force
```

> `migrate:fresh` **drops every table**. Never run it against live data.

---

## 8. Your first walkthrough

Going from an empty system to a finished sale with the profit split. About
ten minutes.

**Step 1 — Register a business.**
Sign out, go to `/register`, fill in the form.

**Step 2 — Confirm the email.**
In local development there is no mail server, so the email is written to the
log. Get the link with:

```bash
grep -o 'http://localhost:8000/email/verify[^"]*' storage/logs/laravel.log | tail -1
```

Open it.

**Step 3 — Activate the account.**
Sign in as `admin@example.com` → **Customer accounts** → open the pending
request → choose a plan → **Activate**. This creates the chart of accounts,
the starter expense categories and the approval rules.

**Step 4 — Sign in as the business owner**, then inside the workspace:

1. **Cash and bank → Add account.** Do this first — nothing can be paid or
   received without an account.
2. **Properties → Add property.**
3. **Purchases → Record purchase** → open it → **Confirm purchase.**
   *This is the moment the price becomes recorded cost.*
4. **Expenses → Add expense** → **Confirm.**
5. **Properties → open it → Investors** → allocate capital.
6. **Sales → Create sale** → set the installment plan → record receipts →
   add sale expenses → **Finalise sale.**
7. Open the sale again — each investor's profit share is now listed, and you
   can pay them.
8. **Reports** → any of the twelve → **Excel / CSV** or **PDF**.

---

## 9. Running the tests

```bash
php artisan test
```

```
Tests:    63 passed (314 assertions)
```

Run one group at a time:

```bash
php artisan test --filter=FinancialIntegrityTest      # the money rules
php artisan test --filter=TenantIsolationTest         # the security rules
php artisan test --filter=EntitlementsAndApprovalsTest
php artisan test --filter=SmokeTest                   # every screen renders
php artisan test --testsuite=Unit                     # the Money value object
```

| Suite | Tests | What it proves |
|---|---|---|
| `FinancialIntegrityTest` | 21 | Every double-counting rule, reversals, cancellations, corrections, duplicate-submission protection, and that the books always balance |
| `TenantIsolationTest` | 13 | One workspace cannot reach another through pages, ids, downloads, exports or reports; staff limits; deactivated logins; portal scope; Super Admin has no standing access |
| `EntitlementsAndApprovalsTest` | 11 | Plan limits enforced, downgrades delete nothing, approval thresholds, nobody approves their own request |
| `SmokeTest` | 5 | Every screen returns 200; all twelve reports export to CSV and PDF |
| `MoneyTest` | 12 | The `Money` value object directly: exact addition where floats drift, half-up rounding, and that a split always adds back to the original |

Plus one trivial route check, for 63 in total.

The suite runs against **real MySQL, not SQLite**, because the money columns
are `DECIMAL` and these tests are only meaningful on the engine the
application ships on. Every test uses `RefreshDatabase`, so the test database
is rebuilt each run.

---

## 10. Features, with worked examples

### 10.1 Purchase cost is recognised exactly once

**Scenario.** You buy a plot for PKR 1,000,000 and pay the seller in two parts.

| Action | Property cost | Seller payable | Bank |
|---|---|---|---|
| Confirm purchase | **1,000,000** | 1,000,000 | — |
| Pay PKR 300,000 | 1,000,000 | 700,000 | −300,000 |
| Pay PKR 200,000 | 1,000,000 | 500,000 | −200,000 |

The cost never moves. A naive system that added up payment rows would report
PKR 1,500,000 of cost. *Test: `test_seller_installments_settle_the_payable_without_adding_cost_again`*

### 10.2 Capitalised vs period expenses

**Scenario.** Plot bought for 1,000,000, transfer charges 75,000, repairs 50,000.

| Expense | Category flag | Effect |
|---|---|---|
| Transfer charges 75,000 | capitalised | Cost base → **1,075,000** |
| Repairs 50,000 | period | Cost base unchanged; reduces profit |

The cost base matters twice: it caps how much investor capital a property can
take, and it is the figure deducted when the sale is finalised. The flag is
**copied onto the expense when confirmed**, so editing the category later
cannot restate profit you have already reported.

### 10.3 Profit is not cash

**Scenario.** Cost 1,000,000. Sold for 1,400,000 with 40,000 commission. The
buyer has paid only 400,000 so far.

```
Realised profit = 1,400,000 − 1,000,000 − 40,000 = 360,000
Cash in hand    =   400,000
Still owed      = 1,000,000
```

All three appear separately on the sale screen and the dashboard. The sale is
**finalised** even though most of the money has not arrived.
*Test: `test_realised_profit_is_separate_from_cash_received`*

### 10.4 Ownership and profit share are different things

**Scenario.** Cost base 3,150,000. Bilal puts in 2,650,000; you put in 500,000.
But you negotiated a bigger slice of the upside for doing the work.

| Participant | Capital | Ownership % | Profit share % |
|---|---|---|---|
| Bilal | 2,650,000 | 84 | **70** |
| Account holder | 500,000 | 16 | **30** |

Both columns are validated to total 100 **independently**. Leave a percentage
blank and it defaults to the capital ratio.
*Test: `test_ownership_and_profit_percentages_are_validated_separately`*

### 10.5 Profit splits never lose a paisa

**Scenario.** Profit of PKR 100,001 split three ways at 33.3333 / 33.3333 / 33.3334.

Naive rounding gives 33,333.67 + 33,333.67 + 33,333.77 — which does not equal
the total. This system hands the remainder to the largest shares first, so the
three parts add back to **exactly** 100,001.00.
*Test: `test_profit_shares_add_back_to_the_exact_total`*

### 10.6 Payable and paid stay separate

**Scenario.** An investor is owed 200,000. You pay 50,000 now.

| | |
|---|---|
| Profit calculated | 200,000 |
| Paid | 50,000 |
| **Still owed** | **150,000** |

Paying them debits *Investor Profit Payable* — never an expense account — so a
distribution can never masquerade as a property cost.
*Test: `test_investor_distribution_is_not_a_property_expense`*

### 10.7 A cancelled sale keeps its history

**Scenario.** Sold for 6,200,000, took a 600,000 token, the buyer walked away.
You keep 150,000 as a cancellation charge and refund 450,000. Later you sell
to someone else for 6,500,000.

- The first sale stays in the database **forever**, marked cancelled, with the
  reason, the forfeiture and the refund.
- The 150,000 becomes income; the 450,000 goes back out of the bank.
- The property returns to **Available**.
- The second sale is a **brand new record** that points back at the first —
  not an edit of it.

Both appear in the property's sale history.
*Test: `test_a_resale_after_cancellation_is_a_new_record`*

### 10.8 Corrections never rewrite what was already paid

**Scenario.** A sale was finalised at 1,300,000, giving an investor a 300,000
share, of which you already paid 100,000. The agreement price was actually
1,250,000.

| | Before | After |
|---|---|---|
| Realised profit | 300,000 | 250,000 |
| Investor share | 300,000 | 250,000 |
| **Already paid** | 100,000 | **100,000** ← untouched |
| Still owed | 200,000 | 150,000 |

The original figures go to the audit log, the change posts as a separate
adjustment journal with your written reason attached, and only the
*outstanding* part moves.
*Test: `test_a_correction_preserves_the_original_and_adjusts_only_what_is_unpaid`*

### 10.9 Approvals, only where you want them

**Scenario.** You set *Property purchase* to need approval at PKR 5,000,000.

| Purchase | Result |
|---|---|
| PKR 200,000 | Confirms straight away |
| PKR 12,500,000 | Held. A request is raised, **nothing is posted**, and approvers are notified |

Nobody can approve their own request — enforced in the policy *and* again in
the service, so it holds no matter how the code is called.
*Tests: `test_nobody_can_approve_their_own_request`, `test_an_operation_that_needs_approval_cannot_be_completed_first`*

### 10.10 Plan limits restrict, they never delete

**Scenario.** An account holds 3 properties. The administrator drops their
limit to 1.

- All 3 properties remain, fully visible and usable.
- Adding a **4th** is refused, with a message naming the limit.
- Selling one frees a slot, because **sold stock is not active inventory**.

*Tests: `test_losing_an_entitlement_does_not_delete_anything`, `test_the_active_property_limit_is_enforced_and_sold_stock_does_not_count`*

### 10.11 Tenant isolation is real

Typing another workspace's record id into the address bar returns **404** — not
a 403, because a 404 leaks less. This holds for pages, edit screens, file
downloads, report exports and every dropdown endpoint.

Three independent layers must all agree: a global query scope, a policy check
on the object itself, and a route-level role gate.
*Test suite: `TenantIsolationTest`*

### 10.12 The twelve reports

Property status · Property-wise ledger · Purchase and seller payments · Sale
and buyer receivables · Expenses · Realised profit and loss · Investor
investments · Investor distributions · Cash and bank summary · Seller
outstanding · Buyer overdue and installments · Date-wise financial summary

Each has date-range, property and investor filters, and exports to **CSV**
(opens in Excel) and **PDF**. Every export is tenant-scoped, permission-checked
and written to the audit log.

---

## 11. Scheduled jobs and commands

One cron entry drives everything:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

| Command | When | What it does |
|---|---|---|
| `php artisan pms:backup` | 01:30 daily | Database backup, prunes old ones, alerts admins on failure |
| `php artisan pms:send-reminders` | 08:00 daily | Installment due and overdue notices, each logged so it is never sent twice |
| `php artisan pms:create-super-admin` | manual | Creates a platform administrator |

Check what is scheduled:

```bash
php artisan schedule:list
```

The backup uses `mysqldump` when the host provides it and falls back to a pure
PHP dump otherwise, so it works on shared hosting where `exec()` is disabled.

---

## 12. Project layout

```
app/
  Http/Controllers/      one per module, plus Admin/ and InvestorPortal/
  Http/Middleware/       ResolveTenant · EnsureAccountIsUsable · EnsureRole
  Models/                tenant-owned models use the BelongsToTenant trait
  Policies/              authorisation, checked on every single action
  Services/
    Ledger/              ← the ONLY code that writes money
    Purchases/ Expenses/ Sales/ Investors/ Cash/
    Approvals/ Documents/ Reports/ Backup/ Tenancy/ Entitlements/ Audit/
  Support/               Money · Permissions · Entitlements · LedgerAccounts
  Console/Commands/      backup · reminders · create-super-admin
database/
  migrations/            39 migrations
  seeders/               PlanSeeder · DatabaseSeeder · DemoSeeder
resources/views/         64 Blade templates, Bootstrap 5
tests/Feature/           the four suites
docs/
  BUSINESS-RULES.md      every journal, event by event
  DEPLOYMENT.md          hosting, cron, backup and restore
  MASTER PROMPT.*        the original client specification
```

---

## 13. Deployment

Full instructions, written for ordinary cPanel or VPS hosting, are in
[**`docs/DEPLOYMENT.md`**](docs/DEPLOYMENT.md). It covers:

- pointing the domain at `public/` (the single most important step)
- production `.env` settings
- file permissions
- the cron entry
- checking that `.env` and backups are not reachable over the web
- **taking a backup and restoring it**, with a query that proves the restored
  books still balance
- a go-live security checklist

---

## 14. Not built yet

Being explicit, so nothing is assumed:

| Item | Status |
|---|---|
| Two-factor authentication | Database columns exist, no implementation. Spec marked it optional. |
| Account closure / full workspace data export | Report exports work; there is no single "close account and hand over everything" flow. |
| Automatic late-fee calculation | Late fees can be recorded and post to income, but there is no rule engine. The spec warned against penalty engines. |
| Email delivery-failure recording | Not implemented. |
| Payment gateway / automated billing | Out of scope by design — plans are assigned manually. |

**Open business decisions** that should be confirmed before real data goes in:

1. Which expense categories should be **capitalised**? This drives both the
   investor cap and the profit figure. The seeded defaults are a reasonable
   guess, not a decision.
2. Should a loss be recoverable **from** investors, or only reduce their capital?
3. Should support access require the account holder's consent, or is a logged
   reason enough?

---

## 15. Licence

**Proprietary — all rights reserved.** See [`LICENSE`](LICENSE).

The code is public for reference and review. That does **not** grant any right
to use, copy, host or redistribute it. Third-party packages in `vendor/` keep
their own licences.

---

<div align="center">
<sub>Built with Laravel · All amounts in PKR · Verify all figures independently</sub>
</div>
