<?php

namespace App\Services\Payments;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Support\OwnerContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SSLCommerz hosted checkout: one integration for bKash, Nagad, Rocket, cards (Visa/Master/Amex)
 * and internet banking. Each landlord uses their OWN SSLCommerz store, so the money goes straight
 * to the landlord; the platform never holds tenant money.
 */
class OnlinePaymentService
{
    /** Creates a checkout session and returns the gateway page URL to redirect the tenant to. */
    public function start(Charge $charge, float $amount): string
    {
        $account = PaymentAccount::withoutGlobalScopes()->where('user_id', $charge->user_id)->first();
        $owner = User::find($charge->user_id);

        if (! $account?->isReady() || ! $owner?->hasActiveSubscription() || ! $owner->hasFeature('bkash_collection')) {
            throw new PaymentException('এই মুহূর্তে অনলাইন পেমেন্ট চালু নেই।');
        }

        $amount = round($amount, 2);

        if ($amount < 1 || $amount > $charge->dueAmount()) {
            throw new PaymentException('টাকার পরিমাণ ঠিক নেই (বাকি: ৳ ' . number_format($charge->dueAmount()) . ')।');
        }

        $tenant = OwnerContext::run(fn () => $charge->lease()->with('tenant')->first()?->tenant);

        $attempt = PaymentAttempt::create([
            'user_id' => $charge->user_id,
            'charge_id' => $charge->id,
            'tran_id' => 'RB' . now()->format('ymdHis') . Str::upper(Str::random(6)),
            'amount' => $amount,
            'status' => 'pending',
        ]);

        $response = Http::asForm()->timeout(25)->post($this->baseUrl($account) . '/gwprocess/v4/api.php', [
            'store_id' => $account->store_id,
            'store_passwd' => $account->store_password,
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $attempt->tran_id,
            'success_url' => route('pay.return', ['result' => 'success', 'tranId' => $attempt->tran_id]),
            'fail_url' => route('pay.return', ['result' => 'fail', 'tranId' => $attempt->tran_id]),
            'cancel_url' => route('pay.return', ['result' => 'cancel', 'tranId' => $attempt->tran_id]),
            'ipn_url' => route('pay.ipn'),
            'cus_name' => $tenant?->name ?? 'Tenant',
            'cus_email' => config('mail.from.address') ?: 'no-reply@example.com',
            'cus_add1' => 'Bangladesh',
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $tenant?->phone ?? '01700000000',
            'shipping_method' => 'NO',
            'product_name' => ucfirst((string) $charge->type) . ' ' . $charge->period->format('M Y'),
            'product_category' => 'Rent',
            'product_profile' => 'general',
        ]);

        $json = $response->json();

        if (($json['status'] ?? null) !== 'SUCCESS' || empty($json['GatewayPageURL'])) {
            $attempt->update(['status' => 'failed', 'payload' => ['init' => $json]]);

            throw new PaymentException('পেমেন্ট গেটওয়ে চালু করা যায়নি, একটু পরে আবার চেষ্টা করুন।');
        }

        $attempt->update(['session_key' => $json['sessionkey'] ?? null]);

        return $json['GatewayPageURL'];
    }

    /**
     * Confirms a payment with SSLCommerz (server to server) and records it. Safe to call many times
     * (success redirect + IPN): the bill is credited only once.
     */
    public function complete(PaymentAttempt $attempt, ?string $valId): bool
    {
        return OwnerContext::run(function () use ($attempt, $valId) {
            return DB::transaction(function () use ($attempt, $valId) {
                $attempt = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->first();

                if ($attempt->status === 'paid') {
                    return true;
                }

                if (! in_array($attempt->status, ['pending', 'failed'], true) || blank($valId)) {
                    return false;
                }

                $account = PaymentAccount::withoutGlobalScopes()->where('user_id', $attempt->user_id)->first();

                if (! $account || blank($account->store_id)) {
                    return false;
                }

                $response = Http::timeout(25)->get($this->baseUrl($account) . '/validator/api/validationserverAPI.php', [
                    'val_id' => $valId,
                    'store_id' => $account->store_id,
                    'store_passwd' => $account->store_password,
                    'format' => 'json',
                ]);

                $result = $response->json();

                if (! is_array($result)) {
                    return false; // gateway unreachable: try again later (IPN or reload)
                }

                $valid = in_array($result['status'] ?? '', ['VALID', 'VALIDATED'], true)
                    && ($result['tran_id'] ?? null) === $attempt->tran_id
                    && abs((float) ($result['amount'] ?? 0) - (float) $attempt->amount) < 0.01;

                if (! $valid) {
                    $attempt->update(['status' => 'failed', 'val_id' => $valId, 'payload' => ['validation' => $result]]);

                    return false;
                }

                $charge = Charge::withoutGlobalScopes()->find($attempt->charge_id);
                $method = $this->methodFromCardType($result['card_type'] ?? null);

                try {
                    $payment = Payment::unguarded(fn () => Payment::create([
                        'user_id' => $attempt->user_id,
                        'charge_id' => $charge->id,
                        'amount' => $attempt->amount,
                        'method' => $method,
                        'trx_id' => $result['bank_tran_id'] ?? $valId,
                        'paid_at' => now(),
                        'note' => 'Online: ' . ($result['card_type'] ?? ''),
                        'source' => 'online',
                        'payment_attempt_id' => $attempt->id,
                    ]));
                } catch (ValidationException $e) {
                    // Money was received but the bill changed meanwhile (e.g. already paid in cash).
                    $attempt->update([
                        'status' => 'needs_review',
                        'val_id' => $valId,
                        'card_type' => $result['card_type'] ?? null,
                        'bank_tran_id' => $result['bank_tran_id'] ?? null,
                        'payload' => ['validation' => $result],
                    ]);

                    return false;
                }

                $attempt->update([
                    'status' => 'paid',
                    'val_id' => $valId,
                    'card_type' => $result['card_type'] ?? null,
                    'bank_tran_id' => $result['bank_tran_id'] ?? null,
                    'payment_id' => $payment->id,
                    'payload' => ['validation' => $result],
                ]);

                return true;
            });
        });
    }

    public function methodFromCardType(?string $cardType): string
    {
        $type = strtoupper((string) $cardType);

        return match (true) {
            str_contains($type, 'BKASH') => 'bkash',
            str_contains($type, 'NAGAD') => 'nagad',
            str_contains($type, 'ROCKET'), str_contains($type, 'DBBLMOBILE') => 'rocket',
            str_contains($type, 'VISA'), str_contains($type, 'MASTER'),
            str_contains($type, 'AMEX'), str_contains($type, 'UNIONPAY') => 'card',
            str_contains($type, 'BANK') => 'bank',
            default => 'online',
        };
    }

    private function baseUrl(PaymentAccount $account): string
    {
        return $account->is_sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }
}
