<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\BuyerPayment;
use App\Models\FinancialAccount;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Ledger\JournalDraft;
use App\Services\Ledger\Ledger;
use App\Services\Sales\BuyerPaymentService;
use App\Support\LedgerAccounts;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ReservationController extends Controller
{
    public function __construct(
        private readonly BuyerPaymentService $payments,
        private readonly AuditLogger $audit,
        private readonly Ledger $ledger,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Reservation::class);

        return view('reservations.index', [
            'reservations' => Reservation::query()
                ->with(['property', 'buyer'])
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Reservation::class);

        return view('reservations.form', [
            'reservation' => new Reservation(['reservation_date' => now()->toDateString()]),
            'properties' => Property::where('status', Property::STATUS_AVAILABLE)->orderBy('reference')->get(),
            'buyers' => Buyer::orderBy('name')->get(),
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'preset' => ['property_id' => $request->integer('property_id')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Reservation::class);

        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'buyer_id' => ['required', 'exists:buyers,id'],
            'reservation_date' => ['required', 'date'],
            'token_amount' => ['required', 'numeric', 'min:0'],
            'agreed_price' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:reservation_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'financial_account_id' => ['nullable', 'required_with:token_amount', 'exists:financial_accounts,id'],
        ]);

        $reservation = DB::transaction(function () use ($data, $request) {
            $reservation = Reservation::create([
                'property_id' => $data['property_id'],
                'buyer_id' => $data['buyer_id'],
                'reservation_date' => $data['reservation_date'],
                'token_amount' => Money::of($data['token_amount'])->toDecimal(),
                'agreed_price' => isset($data['agreed_price']) ? Money::of($data['agreed_price'])->toDecimal() : null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Reservation::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);

            $reservation->property->update(['status' => Property::STATUS_RESERVED]);

            // The token is received as cash but recorded as a buyer advance.
            if (Money::of($data['token_amount'])->isPositive()) {
                $this->payments->record([
                    'reservation_id' => $reservation->id,
                    'buyer_id' => $reservation->buyer_id,
                    'financial_account_id' => $data['financial_account_id'],
                    'payment_date' => $data['reservation_date'],
                    'amount' => $data['token_amount'],
                    'type' => BuyerPayment::TYPE_TOKEN,
                    'method' => 'cash',
                ]);
            }

            return $reservation;
        });

        return redirect()->route('reservations.show', $reservation)
            ->with('status', 'Reservation recorded. The token is held as a buyer advance, not as profit.');
    }

    public function show(Reservation $reservation): View
    {
        $this->authorize('view', $reservation);

        $reservation->load(['property', 'buyer', 'payments.financialAccount', 'documents.currentVersion']);

        return view('reservations.show', [
            'reservation' => $reservation,
            'received' => $reservation->receivedTotal(),
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Reservation $reservation): View
    {
        $this->authorize('update', $reservation);

        return view('reservations.form', [
            'reservation' => $reservation,
            'properties' => Property::orderBy('reference')->get(),
            'buyers' => Buyer::orderBy('name')->get(),
            'accounts' => FinancialAccount::where('is_active', true)->orderBy('name')->get(),
            'preset' => [],
        ]);
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('update', $reservation);

        $data = $request->validate([
            'expiry_date' => ['nullable', 'date'],
            'agreed_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation->update([
            'expiry_date' => $data['expiry_date'] ?? null,
            'agreed_price' => isset($data['agreed_price']) ? Money::of($data['agreed_price'])->toDecimal() : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('reservations.show', $reservation)->with('status', 'Reservation updated.');
    }

    /**
     * Cancel a reservation, handling the token: part of it may be kept as a
     * cancellation charge and the rest refunded.
     */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('update', $reservation);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
            'cancellation_date' => ['required', 'date'],
            'forfeited_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'financial_account_id' => ['nullable', 'required_with:refund_amount', 'exists:financial_accounts,id'],
        ]);

        $received = $reservation->receivedTotal();
        $forfeited = Money::of($data['forfeited_amount'] ?? 0);
        $refund = Money::of($data['refund_amount'] ?? 0);

        if ($forfeited->plus($refund)->greaterThan($received)) {
            return back()->with('error', sprintf(
                'Only %s was received, so the forfeited amount and the refund cannot add up to more than that.',
                $received->format(),
            ))->withInput();
        }

        try {
            DB::transaction(function () use ($reservation, $data, $forfeited, $refund) {
                if ($forfeited->isPositive()) {
                    $this->ledger->post(
                        JournalDraft::make($data['cancellation_date'], 'Reservation cancellation charge: '.$reservation->property->reference)
                            ->debit(LedgerAccounts::BUYER_ADVANCE, $forfeited, $reservation->property_id)
                            ->credit(LedgerAccounts::FORFEITURE_INCOME, $forfeited, $reservation->property_id)
                            ->for($reservation)
                    );
                }

                if ($refund->isPositive()) {
                    $this->payments->record([
                        'reservation_id' => $reservation->id,
                        'buyer_id' => $reservation->buyer_id,
                        'financial_account_id' => $data['financial_account_id'],
                        'payment_date' => $data['cancellation_date'],
                        'amount' => $refund->toDecimal(),
                        'type' => BuyerPayment::TYPE_REFUND,
                        'method' => 'cash',
                    ]);
                }

                $reservation->update([
                    'status' => Reservation::STATUS_CANCELLED,
                    'cancellation_date' => $data['cancellation_date'],
                    'cancellation_reason' => $data['cancellation_reason'],
                    'forfeited_amount' => $forfeited->toDecimal(),
                    'refunded_amount' => $refund->toDecimal(),
                ]);

                if ($reservation->property->status === Property::STATUS_RESERVED) {
                    $reservation->property->update(['status' => Property::STATUS_AVAILABLE]);
                }

                $this->audit->log(
                    'reservation.cancelled',
                    $reservation,
                    sprintf('Forfeited %s, refunded %s', $forfeited->format(), $refund->format()),
                    reason: $data['cancellation_reason'],
                );
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Reservation cancelled. The record has been kept with the settlement details.');
    }

    /** Turn an active reservation into a draft sale, carrying the token across. */
    public function convert(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('update', $reservation);

        if (! $reservation->isActive()) {
            return back()->with('error', 'Only an active reservation can be converted.');
        }

        $data = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:0.01'],
            'agreement_date' => ['required', 'date'],
        ]);

        $sale = DB::transaction(function () use ($reservation, $data, $request) {
            $sale = Sale::create([
                'property_id' => $reservation->property_id,
                'buyer_id' => $reservation->buyer_id,
                'reservation_id' => $reservation->id,
                'agreement_date' => $data['agreement_date'],
                'sale_price' => Money::of($data['sale_price'])->toDecimal(),
                'status' => Sale::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $reservation->property->update(['status' => Property::STATUS_UNDER_SALE]);

            return $sale;
        });

        return redirect()->route('sales.show', $sale)
            ->with('status', 'Draft sale created from the reservation. The token already received counts towards it.');
    }
}
