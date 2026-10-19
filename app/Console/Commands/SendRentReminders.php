<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\OwnerContext;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders';

    protected $description = 'SMS reminders for unpaid bills (days from config/sms.php). Each reminder is sent once.';

    public function handle(SmsService $sms): int
    {
        $offsets = config('sms.reminder_days', [-2, 0, 3, 7]);
        $ahead = abs(min(0, min($offsets)));
        $behind = max(0, max($offsets));
        $sent = 0;

        User::query()
            ->where('is_admin', false)
            ->where('sms_credits', '>', 0)
            ->each(function (User $owner) use ($sms, $offsets, $ahead, $behind, &$sent) {
                if (! $owner->hasActiveSubscription() || ! $owner->hasFeature('sms_reminders')) {
                    return;
                }

                OwnerContext::run(function () use ($owner, $sms, $offsets, $ahead, $behind, &$sent) {
                    $charges = Charge::withoutGlobalScopes()
                        ->where('user_id', $owner->id)
                        ->where('status', '!=', Charge::STATUS_PAID)
                        ->whereDate('due_on', '>=', today()->subDays($behind))
                        ->whereDate('due_on', '<=', today()->addDays($ahead))
                        ->get();

                    foreach ($charges as $charge) {
                        $late = $sms->daysLate($charge);

                        if (! in_array($late, $offsets, true)) {
                            continue;
                        }

                        $kind = 'r' . $late;

                        $already = SmsLog::where('charge_id', $charge->id)
                            ->where('kind', $kind)
                            ->whereIn('status', ['sent', 'queued', 'invalid_number'])
                            ->exists();

                        if ($already) {
                            continue;
                        }

                        if ($sms->sendReminder($charge, $kind)?->status === 'sent') {
                            $sent++;
                        }
                    }
                });
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
