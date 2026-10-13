<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const TYPE_APPROVAL_REQUEST = 'approval_request';
    public const TYPE_APPROVAL_DECISION = 'approval_decision';
    public const TYPE_INSTALLMENT_DUE = 'installment_due';
    public const TYPE_INSTALLMENT_OVERDUE = 'installment_overdue';
    public const TYPE_SECURITY = 'security';
    public const TYPE_BACKUP_FAILED = 'backup_failed';

    public const TYPES = [
        self::TYPE_APPROVAL_REQUEST => 'Approval requests waiting for me',
        self::TYPE_APPROVAL_DECISION => 'Decisions on requests I raised',
        self::TYPE_INSTALLMENT_DUE => 'Buyer installments falling due',
        self::TYPE_INSTALLMENT_OVERDUE => 'Buyer installments overdue',
        self::TYPE_SECURITY => 'Account and security events',
        self::TYPE_BACKUP_FAILED => 'Backup failures',
    ];

    protected $fillable = ['user_id', 'type', 'in_app', 'email'];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
