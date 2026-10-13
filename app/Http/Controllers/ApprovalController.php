<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalManager $approvals)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        return view('approvals.index', [
            'requests' => ApprovalRequest::query()
                ->with(['requester', 'approver', 'approvable'])
                ->when(
                    $request->string('status')->toString() ?: ApprovalRequest::STATUS_PENDING,
                    fn ($q, $s) => $q->where('status', $s)
                )
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $request->string('status')->toString() ?: ApprovalRequest::STATUS_PENDING,
        ]);
    }

    public function show(ApprovalRequest $approval): View
    {
        $this->authorize('view', $approval);

        $approval->load(['requester', 'approver', 'approvable']);

        return view('approvals.show', ['request' => $approval]);
    }

    public function approve(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('decide', $approval);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->approvals->approve($approval, $request->user(), $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Approved. The requester can now complete the operation.');
    }

    public function reject(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('decide', $approval);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->approvals->reject($approval, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Rejected, with your reason recorded.');
    }
}
