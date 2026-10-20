<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * For hosts without a real cron (e.g. Render free): an external pinger (cron-job.org) calls
 * /cron/{secret} every 5 minutes. That ping also keeps a free web service from falling asleep.
 * Both tasks are idempotent, so extra or repeated calls are harmless.
 */
class CronController extends Controller
{
    public function run(string $token)
    {
        $expected = (string) config('cron.token');

        // 404 (not 403) so the URL does not even reveal that it exists.
        abort_unless(strlen($expected) >= 24 && hash_equals($expected, $token), 404);

        $lock = Cache::lock('cron-run', 120);

        if (! $lock->get()) {
            return response()->json(['ok' => true, 'skipped' => 'already running']);
        }

        $remindersChecked = false;

        try {
            Artisan::call('rent:generate-charges');

            $hour = (int) now()->format('G'); // app timezone, set APP_TIMEZONE=Asia/Dhaka

            if ($hour >= 9 && $hour < 20) {
                Artisan::call('rent:send-reminders');
                $remindersChecked = true;
            }
        } finally {
            $lock->release();
        }

        return response()->json([
            'ok' => true,
            'reminders_checked' => $remindersChecked,
            'time' => now()->toIso8601String(),
        ]);
    }
}
