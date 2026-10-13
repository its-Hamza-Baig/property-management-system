<?php

namespace App\Models;

use App\Services\Tenancy\TenantContext;
use App\Support\Permissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ACCOUNT_HOLDER = 'account_holder';
    public const ROLE_STAFF = 'staff';
    public const ROLE_INVESTOR = 'investor';

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role', 'permissions',
        'investor_id', 'is_active', 'phone', 'email_verified_at',
        // Accounts created from inside the workspace (staff, investor portal)
        // are already trusted, so they are marked verified on creation. Every
        // array passed to create() here is built explicitly from validated
        // input; no controller mass-assigns raw request data.
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAccountHolder(): bool
    {
        return $this->role === self::ROLE_ACCOUNT_HOLDER;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isInvestor(): bool
    {
        return $this->role === self::ROLE_INVESTOR;
    }

    /**
     * Account holders hold every workspace permission implicitly. Staff hold
     * only what was granted.
     *
     * A Super Admin holds nothing by default: the platform role gives no
     * business-data rights at all. The single exception is a live support
     * grant, which is read only, time boxed, and logged into the audit trail
     * of the customer on every page.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAccountHolder()) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return app(TenantContext::class)->isSupportAccess()
                && str_ends_with($permission, '.view');
        }

        if (! $this->isStaff()) {
            return false;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        if ($this->isAccountHolder()) {
            return Permissions::all();
        }

        return $this->permissions ?? [];
    }

    public function twoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ACCOUNT_HOLDER => 'Account Holder',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_INVESTOR => 'Investor',
            default => ucfirst($this->role),
        };
    }
}
