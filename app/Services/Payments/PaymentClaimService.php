<?php

namespace App\Services\Payments;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Support\OwnerContext;
use Illuminate\Support\Facades\DB;

class PaymentClaimService
{
    /** Methods a tenant can claim (cash is recorded by the landlord; online goes through the gateway). */
    public const METHODS = ['bkash', 'nagad', 'rocket', 'bank', 'card'];

    private const MAX_PENDING_PER_BILL = 3;

    /** Tenant side. Throws PaymentException with a message that is safe to show. */
    public function submit(Charge $charge, array $data): PaymentClaim
    {
        return OwnerContext::run(function () use ($charge, $data) {
            $charge = Charge::withoutGlobalScopes()->findOrFail($charge->id);

            $amount = round((float) ($data['amount'] ?? 0), 2);
            $method = (string) ($data['method'] ?? '');
            $trx = strtoupper(trim((string) ($data['trx_id'] ?? '')));
            $sender = preg_replace('/\D+/', '', (string) ($data['sender_number'] ?? '')) ?: null;

            if ($charge->status === Charge::STATUS_PAID || $charge->dueAmount() <= 0) {
                throw new PaymentException('এই বিল আগেই পরিশোধ হয়ে গেছে।');
            }

            if ($amount < 1 || $amount > $charge->dueAmount()) {
                throw new PaymentException('টাকার পরিমাণ ঠিক নেই (বাকি: ৳ ' . number_format($charge->dueAmount()) . ')।');
            }

            if (! in_array($method, self::METHODS, true)) {
                throw new PaymentException('পেমেন্টের মাধ্যম বেছে নিন।');
            }

            if (strlen($trx) < 4 || strlen($trx) > 60 || ! preg_match('/^[A-Z0-9\-_\/\. ]+$/', $trx)) {
                throw new PaymentException('Transaction ID সঠিকভাবে লিখুন (অক্ষর ও সংখ্যা, কমপক্ষে ৪ ঘর)।');
            }

            $pending = PaymentClaim::withoutGlobalScopes()
                ->where('charge_id', $charge->id)
                ->where('status', PaymentClaim::STATUS_PENDING)
                ->count();

            if ($pending >= self::MAX_PENDING_PER_BILL) {
                throw new PaymentException('এই বিলের জন্য আগের তথ্যগুলো এখনো যাচাই হয়নি। বাড়িওয়ালা যাচাই করা পর্যন্ত অপেক্ষা করুন।');
            }

            $alreadyUsed = PaymentClaim::withoutGlobalScopes()
                    ->where('user_id', $charge->user_id)
                    ->whereRaw('UPPER(trx_id) = ?', [$trx])
                    ->whereIn('status', [PaymentClaim::STATUS_PENDING, PaymentClaim::STATUS_CONFIRMED])
                    ->exists()
                || Payment::withoutGlobalScopes()
                    ->where('user_id', $charge->user_id)
                    ->whereRaw('UPPER(trx_id) = ?', [$trx])
                    ->exists();

            if ($alreadyUsed) {
                throw new PaymentException('এই Transaction ID আগেই জমা দেওয়া হয়েছে।');
            }

            return PaymentClaim::unguarded(fn () => PaymentClaim::create([
                'user_id' => $charge->user_id,
                'charge_id' => $charge->id,
                'amount' => $amount,
                'method' => $method,
                'trx_id' => $trx,
                'sender_number' => $sender ? substr($sender, 0, 20) : null,
                'note' => isset($data['note']) ? substr(trim((string) $data['note']), 0, 255) : null,
                'status' => PaymentClaim::STATUS_PENDING,
            ]));
        });
    }

    /** Landlord side: turns the claim into a real payment (amount may be corrected). */
    public function confirm(PaymentClaim $claim, ?float $amount = null): Payment
    {
        return OwnerContext::run(fn () => DB::transaction(function () use ($claim, $amount) {
            $claim = PaymentClaim::withoutGlobalScopes()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->status !== PaymentClaim::STATUS_PENDING) {
                throw new PaymentException('এই দাবি আগেই নিষ্পত্তি হয়েছে।');
            }

            $charge = Charge::withoutGlobalScopes()->findOrFail($claim->charge_id);
            $amount = round($amount ?? (float) $claim->amount, 2);

            if ($amount < 1 || $amount > $charge->dueAmount()) {
                throw new PaymentException('টাকার পরিমাণ বাকির চেয়ে বেশি (বাকি: ৳ ' . number_format($charge->dueAmount()) . ')।');
            }

            $payment = Payment::unguarded(fn () => Payment::create([
                'user_id' => $claim->user_id,
                'charge_id' => $charge->id,
                'amount' => $amount,
                'method' => $claim->method,
                'trx_id' => $claim->trx_id,
                'paid_at' => now(),
                'note' => 'ভাড়াটিয়ার দাবি যাচাই করা হয়েছে' . ($claim->sender_number ? " ({$claim->sender_number})" : ''),
                'source' => 'claim',
            ]));

            $claim->update([
                'status' => PaymentClaim::STATUS_CONFIRMED,
                'payment_id' => $payment->id,
                'reviewed_at' => now(),
            ]);

            return $payment;
        }));
    }

    public function reject(PaymentClaim $claim, ?string $reason = null): void
    {
        OwnerContext::run(fn () => DB::transaction(function () use ($claim, $reason) {
            $claim = PaymentClaim::withoutGlobalScopes()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->status !== PaymentClaim::STATUS_PENDING) {
                throw new PaymentException('এই দাবি আগেই নিষ্পত্তি হয়েছে।');
            }

            $claim->update([
                'status' => PaymentClaim::STATUS_REJECTED,
                'review_note' => $reason ? substr(trim($reason), 0, 255) : null,
                'reviewed_at' => now(),
            ]);
        }));
    }
}
