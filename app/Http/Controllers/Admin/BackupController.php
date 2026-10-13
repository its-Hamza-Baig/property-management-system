<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', [
            'runs' => BackupRun::orderByDesc('id')->paginate(25),
            'lastSuccess' => BackupRun::where('status', BackupRun::STATUS_SUCCESS)->latest('id')->first(),
            'retention' => config('pms.backup.retention_days'),
            'disk' => config('pms.backup.disk'),
        ]);
    }

    public function run(): RedirectResponse
    {
        Artisan::call('pms:backup');

        $run = BackupRun::latest('id')->first();

        if ($run?->status === BackupRun::STATUS_FAILED) {
            return back()->with('error', 'The backup failed: '.$run->error);
        }

        return back()->with('status', 'Backup completed: '.$run?->filename);
    }
}
