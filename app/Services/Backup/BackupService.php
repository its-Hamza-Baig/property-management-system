<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Daily database backup.
 *
 * Uses mysqldump when the host provides it, and otherwise falls back to a
 * plain PHP dump, because a lot of shared hosting disables exec(). Either way
 * the output is a .sql file on a private disk, and every attempt is recorded
 * in backup_runs so a silent failure is visible on the platform dashboard.
 */
class BackupService
{
    public function run(): BackupRun
    {
        $run = BackupRun::create([
            'type' => 'database',
            'status' => BackupRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $disk = Storage::disk(config('pms.backup.disk'));
        $filename = 'pms-'.now()->format('Y-m-d-His').'.sql';

        try {
            $sql = $this->dump();

            $disk->put($filename, $sql);

            $run->update([
                'status' => BackupRun::STATUS_SUCCESS,
                'filename' => $filename,
                'size_bytes' => strlen($sql),
                'finished_at' => now(),
            ]);

            $this->prune();
        } catch (Throwable $e) {
            $run->update([
                'status' => BackupRun::STATUS_FAILED,
                'error' => substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);
        }

        return $run->refresh();
    }

    private function dump(): string
    {
        return $this->mysqldumpAvailable() ? $this->dumpWithMysqldump() : $this->dumpWithPhp();
    }

    private function mysqldumpAvailable(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $process = Process::fromShellCommandline('command -v mysqldump');
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function dumpWithMysqldump(): string
    {
        $config = config('database.connections.'.config('database.default'));

        $process = new Process([
            'mysqldump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--password='.$config['password'],
            '--single-transaction',
            '--routines',
            '--no-tablespaces',
            $config['database'],
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Portable fallback. Produces a restorable .sql file using only PDO, so
     * it works where shell access is unavailable.
     */
    private function dumpWithPhp(): string
    {
        $database = DB::connection()->getDatabaseName();

        $lines = [
            '-- Property Management System backup',
            '-- Database: '.$database,
            '-- Taken: '.now()->toDateTimeString(),
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET NAMES utf8mb4;',
            '',
        ];

        foreach ($this->tables() as $table) {
            $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = $create->{'Create Table'} ?? ($create->{'Create View'} ?? null);

            if ($createSql === null) {
                continue;
            }

            $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
            $lines[] = $createSql.';';
            $lines[] = '';

            // Chunked so a large table cannot exhaust memory.
            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$lines, $table) {
                $values = [];

                foreach ($rows as $row) {
                    $cells = array_map(
                        fn ($value) => $this->quote($value),
                        (array) $row,
                    );
                    $values[] = '('.implode(',', $cells).')';
                }

                if ($values !== []) {
                    $columns = implode('`,`', array_keys((array) $rows->first()));
                    $lines[] = "INSERT INTO `{$table}` (`{$columns}`) VALUES ".implode(',', $values).';';
                }
            });

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines);
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    /** @return list<string> */
    private function tables(): array
    {
        $database = DB::connection()->getDatabaseName();
        $rows = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $key = 'Tables_in_'.$database;

        return array_map(fn ($row) => $row->{$key}, $rows);
    }

    /** Delete backups older than the configured retention. */
    public function prune(): int
    {
        $disk = Storage::disk(config('pms.backup.disk'));
        $cutoff = now()->subDays((int) config('pms.backup.retention_days'))->getTimestamp();
        $removed = 0;

        foreach ($disk->files() as $file) {
            if (! str_ends_with($file, '.sql')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
