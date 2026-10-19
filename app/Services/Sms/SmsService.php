<?php

namespace App\Services\Sms;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\SmsLog;
use App\Models\User;
use App\Support\OwnerContext;
use App\Support\Phone;
use Illuminate\Support\Str;

class SmsService
{
    /**
     * Sends one SMS on behalf of a landlord. Costs 1 SMS credit (set by the admin).
     * No credit, invalid number or gateway failure never throws: the result is stored in sms_logs.
     */
    public function send(
        User $owner,
        string $phone,
        string $message,
        ?int $tenantId = null,
        ?int $chargeId = null,
        string $kind = 'manual',
    ): SmsLog {
        $phone = Phone::normalize($phone);

        $log = SmsLog::create([
            'user_id' => $owner->getKey(),
            'tenant_id' => $tenantId,
            'charge_id' => $chargeId,
            'kind' => $kind,
            'phone' => $phone,
            'message' => $message,
            'status' => 'queued',
        ]);

        if (! Phone::isValid($phone)) {
            $log->update(['status' => 'invalid_number']);

            return $log;
        }

        // Reserve one credit first (atomic), give it back if the gateway fails.
        $reserved = User::whereKey($owner->getKey())->where('sms_credits', '>', 0)->decrement('sms_credits');

        if ($reserved === 0) {
            $log->update(['status' => 'no_credit']);

            return $log;
        }

        try {
            [$ok, $response] = $this->driver()->send($phone, $message);
        } catch (\Throwable $e) {
            [$ok, $response] = [false, $e->getMessage()];
        }

        if (! $ok) {
            User::whereKey($owner->getKey())->increment('sms_credits');
        }

        $log->update([
            'status' => $ok ? 'sent' : 'failed',
            'provider_response' => Str::limit((string) $response, 500),
        ]);

        return $log;
    }

    /** For the admin test command: goes straight to the configured driver (no credit, no log). */
    public function sendRaw(string $phone, string $message): array
    {
        return $this->driver()->send(Phone::normalize($phone), $message);
    }

    /** Reminder for one bill (kind: manual, or r-2 / r0 / r3 / r7 from the scheduler). */
    public function sendReminder(Charge $charge, string $kind = 'manual'): ?SmsLog
    {
        return OwnerContext::run(function () use ($charge, $kind) {
            $charge->loadMissing('lease.tenant');

            $owner = User::find($charge->user_id);
            $tenant = $charge->lease?->tenant;

            if (! $owner || ! $tenant) {
                return null;
            }

            $late = $this->daysLate($charge);
            $template = $late < 0 ? 'upcoming' : ($late === 0 ? 'due' : 'overdue');

            return $this->send(
                $owner,
                $tenant->phone,
                $this->compose($template, $this->vars($charge, $owner, $tenant->name, $charge->dueAmount())),
                $tenant->id,
                $charge->id,
                $kind,
            );
        });
    }

    /** "Payment received" SMS with the receipt link (only if the landlord has the SMS feature). */
    public function sendReceipt(Payment $payment): ?SmsLog
    {
        return OwnerContext::run(function () use ($payment) {
            $owner = User::find($payment->user_id);

            if (! $owner || ! $owner->hasActiveSubscription() || ! $owner->hasFeature('sms_reminders') || ! $owner->hasFeature('receipt_pdf')) {
                return null;
            }

            $charge = Charge::withoutGlobalScopes()->with('lease.tenant')->find($payment->charge_id);
            $tenant = $charge?->lease?->tenant;

            if (! $charge || ! $tenant) {
                return null;
            }

            $vars = $this->vars($charge, $owner, $tenant->name, (float) $payment->amount);
            $vars['link'] = $payment->receiptUrl();

            return $this->send(
                $owner,
                $tenant->phone,
                $this->compose('receipt', $vars),
                $tenant->id,
                $charge->id,
                'receipt',
            );
        });
    }

    /** Days after the due date (negative = before). */
    public function daysLate(Charge $charge): int
    {
        return -(int) round(today()->diffInDays($charge->due_on->copy()->startOfDay(), false));
    }

    public function compose(string $key, array $vars): string
    {
        $template = config('sms.templates.' . config('sms.language', 'en') . '.' . $key)
            ?? config('sms.templates.en.' . $key);

        $replace = [];

        foreach ($vars as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }

        return strtr((string) $template, $replace);
    }

    private function vars(Charge $charge, User $owner, string $tenantName, float $amount): array
    {
        return [
            'name' => $tenantName,
            'type' => config('sms.types.' . $charge->type, 'bill'),
            'amount' => number_format($amount),
            'month' => $charge->period->format('M Y'),
            'date' => $charge->due_on->format('d M'),
            'link' => $charge->payUrl(),
            'landlord' => $owner->name,
        ];
    }

    private function driver(): SmsDriver
    {
        return match (config('sms.driver')) {
            'bulksmsbd' => new BulkSmsBdDriver(),
            default => new LogDriver(),
        };
    }
}
