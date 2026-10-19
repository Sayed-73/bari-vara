<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\PaymentAccount;
use App\Models\PaymentClaim;
use App\Models\User;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentClaimService;
use App\Services\Payments\PaymentException;
use App\Support\OwnerContext;
use Illuminate\Http\Request;

/** Public page a tenant opens from the SMS/link: bill details, receipts, and the pay button. */
class PublicBillController extends Controller
{
    public function show(string $token)
    {
        $html = OwnerContext::run(function () use ($token) {
            $charge = Charge::withoutGlobalScopes()
                ->where('pay_token', $token)
                ->with(['lease.unit.property', 'lease.tenant', 'payments'])
                ->firstOrFail();

            $owner = User::findOrFail($charge->user_id);
            $account = PaymentAccount::withoutGlobalScopes()->where('user_id', $owner->id)->first();

            $canPayOnline = $account?->isReady()
                && $owner->hasActiveSubscription()
                && $owner->hasFeature('bkash_collection')
                && $charge->dueAmount() > 0;

            return view('public.bill', [
                'charge' => $charge,
                'owner' => $owner,
                'tenant' => $charge->lease->tenant,
                'unit' => $charge->lease->unit,
                'property' => $charge->lease->unit->property,
                'payments' => $charge->payments->sortByDesc('paid_at'),
                'canPayOnline' => $canPayOnline,
                'instructions' => $account?->manual_instructions,
                'claims' => PaymentClaim::withoutGlobalScopes()
                    ->where('charge_id', $charge->id)
                    ->whereIn('status', [PaymentClaim::STATUS_PENDING, PaymentClaim::STATUS_REJECTED])
                    ->latest('id')
                    ->limit(5)
                    ->get(),
                'claimMethods' => array_intersect_key(
                    \App\Models\Payment::METHODS,
                    array_flip(PaymentClaimService::METHODS),
                ),
            ])->render();
        });

        return response($html);
    }

    /** Tenant says "I have paid" and gives the Transaction ID; the landlord verifies it. */
    public function claim(Request $request, string $token, PaymentClaimService $service)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'string', 'max:20'],
            'trx_id' => ['required', 'string', 'max:60'],
            'sender_number' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            OwnerContext::run(function () use ($token, $data, $service) {
                $charge = Charge::withoutGlobalScopes()->where('pay_token', $token)->firstOrFail();

                $service->submit($charge, $data);
            });
        } catch (PaymentException $e) {
            return redirect()->route('bill.show', $token)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bill.show', $token)
            ->with('status', 'আপনার পেমেন্টের তথ্য জমা হয়েছে। বাড়িওয়ালা যাচাই করলে রসিদ পাবেন।');
    }

    public function pay(Request $request, string $token, OnlinePaymentService $service)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        try {
            $url = OwnerContext::run(function () use ($token, $data, $service) {
                $charge = Charge::withoutGlobalScopes()->where('pay_token', $token)->firstOrFail();

                return $service->start($charge, (float) $data['amount']);
            });
        } catch (PaymentException $e) {
            return redirect()->route('bill.show', $token)->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
