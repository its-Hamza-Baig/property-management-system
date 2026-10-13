@extends('layouts.app')
@section('title', 'Backups')
@section('subheading', 'Daily database backup, kept for '.$retention.' days on the "'.$disk.'" disk.')

@section('actions')
    <form method="POST" action="{{ route('admin.backups.run') }}">
        @csrf
        <button class="btn btn-primary"><i class="bi bi-play"></i> Run a backup now</button>
    </form>
@endsection

@section('content')
    @if (! $lastSuccess)
        <div class="alert alert-warning">
            No successful backup has been recorded yet. Run one now, and make sure the scheduler is running
            (see the deployment notes in <code>docs/DEPLOYMENT.md</code>).
        </div>
    @elseif ($lastSuccess->started_at->lt(now()->subDays(2)))
        <div class="alert alert-danger">
            The last successful backup was {{ $lastSuccess->started_at->diffForHumans() }}. Check that the
            scheduled task is running.
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Started</th><th>Type</th><th>Status</th><th>File</th><th>Size</th><th>Duration</th><th>Error</th></tr>
                </thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="small text-nowrap">{{ pms_date($run->started_at, 'd M Y H:i') }}</td>
                        <td>{{ ucfirst($run->type) }}</td>
                        <td>
                            <span class="badge {{ match ($run->status) {
                                'success' => 'bg-success', 'failed' => 'bg-danger', default => 'bg-secondary',
                            } }}">{{ ucfirst($run->status) }}</span>
                        </td>
                        <td class="small">{{ $run->filename ?: '-' }}</td>
                        <td class="small">{{ $run->humanSize() }}</td>
                        <td class="small">{{ $run->durationLabel() }}</td>
                        <td class="small text-danger">{{ \Illuminate\Support\Str::limit($run->error, 80) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No backups have run yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $runs->links() }}</div>

    <div class="card mt-3">
        <div class="card-body small text-muted">
            <strong>Restoring.</strong> A backup is only real once it has been restored successfully at least once.
            The procedure, including how to verify that documents and database rows still match, is in
            <code>docs/DEPLOYMENT.md</code>.
        </div>
    </div>
@endsection
