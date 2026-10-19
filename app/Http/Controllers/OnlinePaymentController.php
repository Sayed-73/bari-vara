<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Sms\SmsService;
use App\Support\OwnerContext;
use Illuminate\Http\Request;

/** Where SSLCommerz sends the tenant back (success/fail/cancel) and its server-to-server IPN. */
class OnlinePaymentController extends Controller
{
    public function back(Request $request, string $result, string $tranId, OnlinePaymentService $service, SmsService $sms)
    {
        $attempt = PaymentAttempt::where('tran_id', $tranId)->firstOrFail();

        $token = OwnerContext::run(fn () => Charge::withoutGlobalScopes()->whereKey($attempt->charge_id)->value('pay_token'));

        if ($result === 'success') {
            if ($service->complete($attempt, $request->input('val_id'))) {
                $this->sendReceiptOnce($attempt->fresh(), $sms);

                return redirect()->route('bill.show', $token)->with('status', 'পেমেন্ট সফল হয়েছে। ধন্যবাদ!');
            }

            return redirect()->route('bill.show', $token)
                ->with('error', 'পেমেন্ট এখনো নিশ্চিত হয়নি। টাকা কেটে থাকলে কিছুক্ষণ পর এই পেজ রিফ্রেশ করুন বা বাড়িওয়ালাকে জানান।');
        }

        if ($attempt->status === 'pending') {
            $attempt->update(['status' => $result === 'cancel' ? 'cancelled' : 'failed']);
        }

        return redirect()->route('bill.show', $token)
            ->with('error', $result === 'cancel' ? 'পেমেন্ট বাতিল করা হয়েছে।' : 'পেমেন্ট সম্পন্ন হয়নি, আবার চেষ্টা করুন।');
    }

    /** Server-to-server confirmation (catches payments where the tenant closed the browser). */
    public function ipn(Request $request, OnlinePaymentService $service, SmsService $sms)
    {
        $attempt = PaymentAttempt::where('tran_id', (string) $request->input('tran_id'))->first();

        if ($attempt && in_array($request->input('status'), ['VALID', 'VALIDATED'], true)) {
            if ($service->complete($attempt, $request->input('val_id'))) {
                $this->sendReceiptOnce($attempt->fresh(), $sms);
            }
        }

        return response('OK');
    }

    private function sendReceiptOnce(PaymentAttempt $attempt, SmsService $sms): void
    {
        if ($attempt->status !== 'paid' || ! $attempt->payment_id) {
            return;
        }

        $payment = OwnerContext::run(fn () => Payment::withoutGlobalScopes()->find($attempt->payment_id));

        if ($payment && ! \App\Models\SmsLog::where('charge_id', $payment->charge_id)->where('kind', 'receipt')->where('message', 'like', '%' . $payment->receipt_token . '%')->exists()) {
            $sms->sendReceipt($payment);
        }
    }
}
