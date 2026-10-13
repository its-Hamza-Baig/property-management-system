<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    public const CATEGORIES = [
        'purchase_agreement' => 'Purchase agreement',
        'sale_agreement' => 'Sale agreement',
        'receipt' => 'Receipt',
        'expense_bill' => 'Expense bill',
        'payment_proof' => 'Payment proof',
        'identity' => 'Identity document',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'documentable_type', 'documentable_id', 'category', 'title',
        'notes', 'uploaded_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Other';
    }
}
