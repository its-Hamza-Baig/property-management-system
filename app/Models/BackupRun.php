<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type', 'status', 'filename', 'size_bytes', 'started_at', 'finished_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }

    public function durationLabel(): string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return '-';
        }

        return $this->started_at->diffInSeconds($this->finished_at).' s';
    }
}
