<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Seller::class);

        return view('sellers.index', [
            'sellers' => Seller::query()
                ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
                ->withCount('purchaseShares')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Seller::class);

        return view('sellers.form', ['seller' => new Seller()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Seller::class);

        $seller = Seller::create($this->validated($request));
        $this->audit->log('seller.created', $seller, $seller->name);

        return redirect()->route('sellers.index')->with('status', 'Seller added.');
    }

    public function edit(Seller $seller): View
    {
        $this->authorize('update', $seller);

        return view('sellers.form', ['seller' => $seller]);
    }

    public function update(Request $request, Seller $seller): RedirectResponse
    {
        $this->authorize('update', $seller);

        $before = $seller->only(['name', 'phone', 'cnic', 'address']);
        $seller->update($this->validated($request));
        $this->audit->logChange('seller.updated', $seller, $before);

        return redirect()->route('sellers.index')->with('status', 'Seller updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnic' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
