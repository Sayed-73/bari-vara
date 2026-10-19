<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Support\OwnerContext;

class ReceiptController extends Controller
{
    public function show(string $token)
    {
        $html = OwnerContext::run(function () use ($token) {
            $payment = Payment::withoutGlobalScopes()
                ->where('receipt_token', $token)
                ->with(['charge.lease.unit.property', 'charge.lease.tenant'])
                ->firstOrFail();

            $owner = User::findOrFail($payment->user_id);

            // Admin can switch receipts off per plan or per landlord at any time.
            abort_unless($owner->hasFeature('receipt_pdf'), 404);

            return view('public.receipt', [
                'payment' => $payment,
                'charge' => $payment->charge,
                'owner' => $owner,
                'tenant' => $payment->charge->lease->tenant,
                'unit' => $payment->charge->lease->unit,
                'property' => $payment->charge->lease->unit->property,
            ])->render();
        });

        return response($html);
    }
}
