<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Buyer::class);

        return view('buyers.index', [
            'buyers' => Buyer::query()
                ->when($request->string('q')->toString(), function ($query, $term) {
                    $like = '%'.$term.'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like));
                })
                ->withCount('sales', 'reservations')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Buyer::class);

        return view('buyers.form', ['buyer' => new Buyer()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Buyer::class);

        $buyer = Buyer::create($this->validated($request));
        $this->audit->log('buyer.created', $buyer, $buyer->name);

        return redirect()->route('buyers.index')->with('status', 'Buyer added.');
    }

    public function edit(Buyer $buyer): View
    {
        $this->authorize('update', $buyer);

        return view('buyers.form', ['buyer' => $buyer]);
    }

    public function update(Request $request, Buyer $buyer): RedirectResponse
    {
        $this->authorize('update', $buyer);

        $before = $buyer->only(['name', 'phone', 'email', 'cnic', 'address']);
        $buyer->update($this->validated($request));
        $this->audit->logChange('buyer.updated', $buyer, $before);

        return redirect()->route('buyers.index')->with('status', 'Buyer updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
