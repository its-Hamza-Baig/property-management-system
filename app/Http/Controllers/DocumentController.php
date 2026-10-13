<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Property;
use App\Models\PropertyPurchase;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * The record types a document may be attached to. Keeping this as an
     * allow-list is what stops a crafted request pointing a file at an
     * arbitrary model.
     *
     * @var array<string, class-string>
     */
    private const ATTACHABLE = [
        'property' => Property::class,
        'purchase' => PropertyPurchase::class,
        'sale' => Sale::class,
        'expense' => Expense::class,
        'reservation' => Reservation::class,
    ];

    public function __construct(
        private readonly DocumentService $documents,
        private readonly AuditLogger $audit,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $data = $request->validate([
            'owner_type' => ['required', Rule::in(array_keys(self::ATTACHABLE))],
            'owner_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'file' => ['required', 'file', 'max:25600'],
        ]);

        /** @var class-string $modelClass */
        $modelClass = self::ATTACHABLE[$data['owner_type']];

        // Resolved through the tenant-scoped model, so an id belonging to
        // another workspace simply does not exist here.
        $owner = $modelClass::findOrFail($data['owner_id']);

        try {
            $this->documents->upload($owner, $request->file('file'), $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Document uploaded.');
    }

    /**
     * Private files are streamed from disk after the policy check. There is
     * no public URL and no symlink into the web root.
     */
    public function download(Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        $version = $document->currentVersion;

        abort_if($version === null, 404);

        $disk = $this->documents->disk($version);

        abort_unless($disk->exists($version->path), 404);

        $this->audit->log(
            action: 'document.downloaded',
            subject: $document,
            description: $version->original_name,
        );

        return $disk->download($version->path, $version->original_name);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documents->delete($document);

        return back()->with('status', 'Document removed. The file itself is retained for the audit trail.');
    }
}
