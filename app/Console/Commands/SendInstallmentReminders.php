<?php

namespace App\Console\Commands;

use App\Models\Installment;
use App\Models\ReminderLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InstallmentReminder;
use App\Services\Tenancy\TenantContext;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Due-date and overdue reminders for buyer installments.
 *
 * Each reminder is written to reminder_logs under a unique key, so a second
 * run on the same day cannot send the same notice twice. A notification only
 * informs somebody; it never creates or changes a financial record.
 */
class SendInstallmentReminders extends Command
{
    protected $signature = 'pms:send-reminders';

    protected $description = 'Notify staff about buyer installments falling due and installments now overdue';

    public function handle(TenantContext $context): int
    {
        $daysBefore = (int) config('pms.reminders.installment_due_days_before');
        $sent = 0;

        foreach (Tenant::where('status', Tenant::STATUS_ACTIVE)->get() as $tenant) {
            $sent += $context->runFor($tenant, function () use ($tenant, $daysBefore) {
                $recipients = $this->recipientsFor($tenant);

                if ($recipients->isEmpty()) {
                    return 0;
                }

                $count = 0;

                $dueSoon = Installment::outstanding()
                    ->whereDate('due_date', now()->addDays($daysBefore)->toDateString())
                    ->with('sale.property', 'sale.buyer')
                    ->get();

                foreach ($dueSoon as $installment) {
                    $count += $this->notify($tenant, $installment, $recipients, false) ? 1 : 0;
                }

                $overdue = Installment::overdue()
                    ->with('sale.property', 'sale.buyer')
                    ->get();

                foreach ($overdue as $installment) {
                    $count += $this->notify($tenant, $installment, $recipients, true) ? 1 : 0;
                }

                return $count;
            });
        }

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function notify(Tenant $tenant, Installment $installment, $recipients, bool $overdue): bool
    {
        $type = $overdue ? 'installment_overdue' : 'installment_due';

        // Overdue notices repeat once a day; a due-date notice only once.
        $referenceDate = $overdue ? now()->toDateString() : $installment->due_date->toDateString();

        try {
            ReminderLog::create([
                'tenant_id' => $tenant->id,
                'type' => $type,
                'remindable_type' => $installment->getMorphClass(),
                'remindable_id' => $installment->id,
                'reference_date' => $referenceDate,
                'sent_at' => now(),
            ]);
        } catch (QueryException) {
            // The unique index rejected it, so this reminder already went out.
            return false;
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new InstallmentReminder($installment, $overdue));
        }

        return true;
    }

    private function recipientsFor(Tenant $tenant)
    {
        return User::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ACCOUNT_HOLDER, User::ROLE_STAFF])
            ->get()
            // Staff only hear about what they are allowed to see.
            ->filter(fn (User $user) => $user->hasPermission(Permissions::SALES_VIEW));
    }
}
