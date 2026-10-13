<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Entitlements\EntitlementManager;
use App\Services\Tenancy\TenantContext;
use App\Support\Entitlements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Uploads and downloads of business documents.
 *
 * Files live on a private disk outside the web root, are validated against
 * the plan entitlements before being accepted, and are only ever served
 * through an authorised controller action.
 */
class DocumentService
{
    public function __construct(
        private readonly EntitlementManager $entitlements,
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {
    }

    public function upload(Model $owner, UploadedFile $file, array $data): Document
    {
        $this->entitlements->enforceDocuments();
        $this->assertFileAllowed($file);

        return DB::transaction(function () use ($owner, $file, $data) {
            $tenantId = $this->context->tenantId();

            // Replacing an existing document adds a version rather than
            // overwriting, so the earlier file stays auditable.
            $document = isset($data['document_id'])
                ? Document::findOrFail($data['document_id'])
                : Document::create([
                    'documentable_type' => $owner->getMorphClass(),
                    'documentable_id' => $owner->getKey(),
                    'category' => $data['category'] ?? 'other',
                    'title' => $data['title'] ?: $file->getClientOriginalName(),
                    'notes' => $data['notes'] ?? null,
                    'uploaded_by' => auth()->id(),
                ]);

            $version = ($document->versions()->max('version') ?? 0) + 1;

            // Stored under a random name inside a per-tenant folder: the path
            // is never derived from anything a user supplies.
            $path = $file->store('tenant-'.$tenantId.'/'.date('Y-m'), 'private');

            if ($path === false) {
                throw new RuntimeException('The file could not be stored. Please try again.');
            }

            DocumentVersion::create([
                'document_id' => $document->id,
                'version' => $version,
                'disk' => 'private',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);

            $this->audit->log(
                action: 'document.uploaded',
                subject: $document,
                description: $file->getClientOriginalName().' (version '.$version.')',
            );

            return $document->refresh();
        });
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $this->audit->log(
                action: 'document.deleted',
                subject: $document,
                description: $document->title,
            );

            // The rows are soft deleted and the files are kept, so a deletion
            // can be investigated later.
            $document->delete();
        });
    }

    /** Total bytes this workspace is currently using. */
    public function storageUsedBytes(): int
    {
        return (int) DocumentVersion::sum('size_bytes');
    }

    private function assertFileAllowed(UploadedFile $file): void
    {
        $maxFileMb = (int) $this->entitlements->value(Entitlements::MAX_FILE_SIZE_MB);
        $allowed = (array) $this->entitlements->value(Entitlements::ALLOWED_FILE_TYPES);
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Files of type .%s are not allowed. Permitted types: %s.',
                $extension,
                implode(', ', $allowed),
            ));
        }

        if ($file->getSize() > $maxFileMb * 1024 * 1024) {
            throw new RuntimeException(sprintf(
                'That file is %s MB. The limit for a single file on your plan is %d MB.',
                number_format($file->getSize() / 1048576, 1),
                $maxFileMb,
            ));
        }

        $quotaMb = (int) $this->entitlements->value(Entitlements::MAX_STORAGE_MB);

        if ($quotaMb >= 0 && ($this->storageUsedBytes() + $file->getSize()) > $quotaMb * 1024 * 1024) {
            throw new RuntimeException(sprintf(
                'This upload would take you over your %d MB storage allowance. Remove some files or ask for a higher limit.',
                $quotaMb,
            ));
        }
    }

    public function streamName(DocumentVersion $version): string
    {
        return $version->original_name;
    }

    public function disk(DocumentVersion $version)
    {
        return Storage::disk($version->disk);
    }
}
