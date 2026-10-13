<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Approvals\Approvable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model implements Approvable
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'property_id', 'sale_id', 'expense_category_id', 'expense_date',
        'amount', 'description', 'status', 'is_capitalized', 'journal_id',
        'created_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'confirmed_at' => 'datetime',
            'is_capitalized' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function fundingSources(): HasMany
    {
        return $this->hasMany(ExpenseFundingSource::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /** Ready to be recognised in the books. */
    public function isConfirmable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }

    public function markApproved(): void
    {
        if ($this->status === self::STATUS_PENDING_APPROVAL) {
            $this->update(['status' => self::STATUS_APPROVED]);
        }
    }

    public function markRejected(?string $reason = null): void
    {
        if ($this->status === self::STATUS_PENDING_APPROVAL) {
            $this->update(['status' => self::STATUS_REJECTED]);
        }
    }

    /** How much of this expense has actually been paid out. */
    public function paidTotal(): Money
    {
        return Money::of(
            $this->payments()->where('status', ExpensePayment::STATUS_CONFIRMED)->sum('amount')
        );
    }

    /**
     * Recognition and payment are different events. The expense hit the books
     * when it was confirmed; this is only what is still owed on it.
     */
    public function outstandingPayable(): Money
    {
        if (! $this->isConfirmed()) {
            return Money::zero();
        }

        return Money::of($this->amount)->minus($this->paidTotal());
    }

    public function paymentStatusLabel(): string
    {
        if (! $this->isConfirmed()) {
            return 'Not recognised';
        }

        $outstanding = $this->outstandingPayable();

        if ($outstanding->isZero()) {
            return 'Paid';
        }

        return $this->paidTotal()->isZero() ? 'Unpaid' : 'Partially paid';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending approval',
            self::STATUS_APPROVED => 'Approved, ready to confirm',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
