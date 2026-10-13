# How the money is recorded

Every financial fact in this system is a **balanced double-entry journal**,
written by one place: `App\Services\Ledger\Ledger::post()`. It refuses any
journal whose debits and credits do not match, and it writes inside a database
transaction, so a half-posted entry cannot exist.

Each concept the business needs to keep separate has its own account. That is
what turns the "do not count this twice" rules from something people have to
remember into something the schema enforces.

## The chart of accounts

Seeded for every workspace when the account is activated.

| Code | Account | Type |
|---|---|---|
| 1000+ | One per cash/bank account | Asset |
| 1100 | Property Cost (Inventory) | Asset |
| 1200 | Buyer Receivable | Asset |
| 2000 | Seller Payable | Liability |
| 2100 | Expense Payable | Liability |
| 2200 | Buyer Advance | Liability |
| 2300 | Investor Profit Payable | Liability |
| 3000 | Investor Capital | Equity |
| 3100 | Owner Capital | Equity |
| 3900 | Retained Result | Equity |
| 4000 | Sale Revenue | Income |
| 4100 | Forfeiture and Cancellation Income | Income |
| 4200 | Late Fee Income | Income |
| 5000 | Cost of Properties Sold | Expense |
| 5100 | Sale Expenses | Expense |
| 5200+ | One per expense category | Expense |

---

## Event by event

### Opening a cash or bank account

| | Debit | Credit |
|---|---|---|
| Opening balance | Cash/Bank | Owner Capital |

Posted once, when the account is created. The opening balance cannot be edited
afterwards, because that would silently restate history; correct it with an
adjustment instead.

### Confirming a property purchase

| | Debit | Credit |
|---|---|---|
| Purchase price | Property Cost | Seller Payable |

**This is the only moment the purchase price becomes cost.**

### Paying a seller

| | Debit | Credit |
|---|---|---|
| Amount paid | Seller Payable | Cash/Bank |

Property Cost is not touched. Ten installments to the seller still leave the
cost at exactly the purchase price.

### Recording an expense

| | Debit | Credit |
|---|---|---|
| Capitalised category | Property Cost | Expense Payable |
| Any other category | Expense: *category* | Expense Payable |

A **capitalised** category (documentation, transfer charges, purchase taxes,
development work) increases the cost base of the property. Everything else
reduces profit in the period.

The flag is copied onto the expense when it is confirmed, so editing the
category later cannot restate profit that has already been reported.

### Paying an expense

| | Debit | Credit |
|---|---|---|
| Amount paid | Expense Payable | Cash/Bank |

The expense itself is untouched. Recognition and payment stay separate, and a
50,000 expense that is paid is still 50,000, not 100,000.

*Who paid* is this journal. *Whose capital it represents* is a separate record
(`expense_funding_sources`), because an investor can fund an expense that the
account holder physically pays.

### Transferring between your own accounts

| | Debit | Credit |
|---|---|---|
| Amount | Destination Cash/Bank | Source Cash/Bank |

No income or expense account is involved, so depositing cash into the bank can
never read as a second sale, and withdrawing can never read as an expense.

### Taking a token or advance from a buyer

| | Debit | Credit |
|---|---|---|
| Amount received | Cash/Bank | Buyer Advance |

Buyer Advance is a **liability**. The money is in the account, but it is not
profit and not revenue.

### Finalising a sale

| | Debit | Credit |
|---|---|---|
| Sale price | Buyer Receivable | Sale Revenue |
| Allocated cost | Cost of Properties Sold | Property Cost |
| Advance already held | Buyer Advance | Buyer Receivable |

Then:

```
realised profit = sale price − allocated cost − sale expenses
```

where *allocated cost* is the purchase price plus every capitalised expense,
and *sale expenses* are the confirmed expenses tagged to that sale.

Both figures are **frozen onto the sale row**, so a later cost edit cannot
restate a closed deal.

A sale can be finalised while the buyer still owes most of the money. The
receivable, the cash received and the profit are three separate numbers and
are reported as such.

### Receiving an installment

| | Debit | Credit |
|---|---|---|
| Amount | Cash/Bank | Buyer Receivable |

If the sale has not been finalised yet the credit goes to Buyer Advance
instead, and finalisation moves it across in one step. Either order gives the
same result, with nothing counted twice.

### Allocating the profit

| | Debit | Credit |
|---|---|---|
| Total profit | Retained Result | — |
| Each investor share | — | Investor Profit Payable |
| Account holder share | — | Owner Capital |

The split uses the profit-sharing percentages **in force on the date of the
sale**, and the remainder is handed to the largest shares first so the parts
add back to exactly the total — no paisa is created or lost.

The account holder share stays in equity, because the business does not owe
itself money.

### Paying an investor

| | Debit | Credit |
|---|---|---|
| Amount | Investor Profit Payable | Cash/Bank |

Never an expense account. A distribution settles a liability; it is not a cost
of the property.

Three different numbers are kept apart throughout: the **calculated share**,
the **amount payable**, and the **amount actually paid**.

### Reversing anything

A reversal is the exact mirror of the original journal, posted as a **new**
journal with `reversal_of_id` pointing back at it. The original is never
edited or deleted.

### Cancelling a sale

1. Every journal for that sale is reversed — revenue, cost and receivable all
   go back, and the property returns to inventory.
2. Any amount forfeited moves from Buyer Advance to Forfeiture Income.
3. Refunds are recorded as a buyer payment of type *refund*.
4. Unpaid investor profit is written back to what was actually paid. **Money
   already handed to an investor is never rewritten**; if they were overpaid,
   the balance shows as negative and has to be recovered.
5. The sale record stays forever. A later sale of the same property is a **new
   record**, never an overwrite.

### Correcting a finalised sale

The account holder can correct a finalised sale with a mandatory written
reason. No Super Admin approval is needed.

- The original figures go into the audit log.
- The change is posted as a separate adjustment journal, flagged
  `is_adjustment`, carrying the reason.
- Investor payables move by the difference only. Amounts already paid are left
  exactly as they are, so a correction can only change what is still
  outstanding.

---

## Investor capital

**The cap.** Total investor capital on a property may not exceed the purchase
price plus capitalised purchase expenses. Whatever is left over is the account
holder's own stake, held as an explicit participant row so every percentage
column adds to 100 without special cases.

**Two ratios, validated separately.** Ownership percentage and profit-sharing
percentage both default to the capital ratio but can be set independently,
because the agreement may say so. Each must total 100 on its own.

**Versioned, not edited.** Changing an allocation closes the old rows with an
`effective_to` date and writes new ones, so the ratios that applied to any past
event remain readable.

**A share transfer is not a sale.** Moving a holding between investors, or to
and from the account holder, settles between those two parties. It never
touches Sale Revenue or realised profit.

---

## Precision

Money is held as **integer paisa** inside `App\Support\Money` and stored in
`DECIMAL(18,2)` columns. No float is ever used in a calculation, and all
rounding happens in that one class.

Multi-step operations run inside `DB::transaction()` with `lockForUpdate()` on
the affected row, and payment forms carry an idempotency key with a unique
index behind it, so a double-submitted form cannot create two payments.

---

## Proving it

`tests/Feature/FinancialIntegrityTest.php` contains one executable test per
rule on this page, including a trial-balance check
(`SUM(debit) − SUM(credit) = 0`) after each scenario.

```bash
php artisan test --filter=FinancialIntegrityTest
```
