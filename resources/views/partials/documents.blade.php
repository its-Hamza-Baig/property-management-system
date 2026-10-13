@php
    use App\Models\Document;
    use App\Support\Entitlements;
    $documentsEnabled = app(\App\Services\Entitlements\EntitlementManager::class)->allows(Entitlements::DOCUMENTS_ENABLED);
@endphp

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Documents</span>
        @unless ($documentsEnabled)
            <span class="badge bg-secondary">Uploads not enabled on your plan</span>
        @endunless
    </div>
    <div class="card-body">
        @if ($documents->isEmpty())
            <div class="text-muted small">No documents attached.</div>
        @else
            <ul class="list-group list-group-flush mb-3">
                @foreach ($documents as $document)
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <a href="{{ route('documents.download', $document) }}">{{ $document->title }}</a>
                            <div class="text-muted small">
                                {{ $document->categoryLabel() }}
                                @if ($document->currentVersion)
                                    &middot; {{ $document->currentVersion->humanSize() }}
                                    &middot; version {{ $document->currentVersion->version }}
                                    &middot; {{ pms_date($document->currentVersion->created_at) }}
                                @endif
                            </div>
                        </div>
                        @can('delete', $document)
                            <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                  onsubmit="return confirm('Remove this document from the record?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($documentsEnabled && auth()->user()->can('create', Document::class))
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="row g-2">
                @csrf
                <input type="hidden" name="owner_type" value="{{ $ownerType }}">
                <input type="hidden" name="owner_id" value="{{ $ownerId }}">

                <div class="col-md-4">
                    <label class="form-label small mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm" required>
                        @foreach (Document::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" @selected(($defaultCategory ?? 'other') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" placeholder="Optional">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">File</label>
                    <input type="file" name="file" class="form-control form-control-sm" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-outline-primary">Upload</button>
                    <span class="text-muted small ms-2">Stored privately. Only people with permission can download it.</span>
                </div>
            </form>
        @endif
    </div>
</div>
