<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseCategory extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const APPLIES_PURCHASE = 'purchase';
    public const APPLIES_PROPERTY = 'property';
    public const APPLIES_SALE = 'sale';
    public const APPLIES_GENERAL = 'general';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'applies_to', 'is_capitalized', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_capitalized' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }

    /**
     * The starter set every new workspace gets. Capitalised categories are the
     * ones that belong in the cost of the property; everything else reduces
     * profit in the period, or at sale.
     *
     * @return list<array{name:string,slug:string,applies_to:string,is_capitalized:bool}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'Documentation and transfer charges', 'slug' => 'documentation-transfer', 'applies_to' => self::APPLIES_PURCHASE, 'is_capitalized' => true],
            ['name' => 'Government taxes and duties on purchase', 'slug' => 'purchase-taxes', 'applies_to' => self::APPLIES_PURCHASE, 'is_capitalized' => true],
            ['name' => 'Purchase agent commission', 'slug' => 'purchase-commission', 'applies_to' => self::APPLIES_PURCHASE, 'is_capitalized' => true],
            ['name' => 'Development and improvement work', 'slug' => 'development-work', 'applies_to' => self::APPLIES_PROPERTY, 'is_capitalized' => true],
            ['name' => 'Repairs and maintenance', 'slug' => 'repairs-maintenance', 'applies_to' => self::APPLIES_PROPERTY, 'is_capitalized' => false],
            ['name' => 'Property tax and utility charges', 'slug' => 'property-tax-utilities', 'applies_to' => self::APPLIES_PROPERTY, 'is_capitalized' => false],
            ['name' => 'Security and caretaking', 'slug' => 'security-caretaking', 'applies_to' => self::APPLIES_PROPERTY, 'is_capitalized' => false],
            ['name' => 'Sale agent or broker commission', 'slug' => 'sale-commission', 'applies_to' => self::APPLIES_SALE, 'is_capitalized' => false],
            ['name' => 'Sale transfer charges', 'slug' => 'sale-transfer-charges', 'applies_to' => self::APPLIES_SALE, 'is_capitalized' => false],
            ['name' => 'Sale taxes and government fees', 'slug' => 'sale-taxes', 'applies_to' => self::APPLIES_SALE, 'is_capitalized' => false],
            ['name' => 'Office and administrative expenses', 'slug' => 'office-admin', 'applies_to' => self::APPLIES_GENERAL, 'is_capitalized' => false],
        ];
    }

    public function appliesToLabel(): string
    {
        return match ($this->applies_to) {
            self::APPLIES_PURCHASE => 'Purchase',
            self::APPLIES_PROPERTY => 'Property holding',
            self::APPLIES_SALE => 'Sale',
            default => 'General',
        };
    }
}
