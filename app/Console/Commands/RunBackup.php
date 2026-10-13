<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\BackupFailed;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'pms:backup';

    protected $description = 'Take a database backup, prune old ones, and alert the administrators if it fails';

    public function handle(BackupService $backups): int
    {
        $this->info('Starting backup...');

        $run = $backups->run();

        if ($run->status === BackupRun::STATUS_SUCCESS) {
            $this->info("Backup written: {$run->filename} ({$run->humanSize()})");

            return self::SUCCESS;
        }

        $this->error('Backup failed: '.$run->error);

        // A backup that fails quietly is worse than no backup at all.
        foreach (User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->get() as $admin) {
            $admin->notify(new BackupFailed($run));
        }

        return self::FAILURE;
    }
}
