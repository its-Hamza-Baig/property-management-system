<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per reminder actually sent. The unique index on
 * (type, remindable, reference_date) is what prevents the scheduler sending
 * the same due-date or overdue notice twice.
 */
class ReminderLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'type', 'remindable_type', 'remindable_id',
        'reference_date', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
