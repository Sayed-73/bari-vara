<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Charge;
use App\Models\PaymentClaim;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RentOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $thisMonth = fn () => Charge::query()
            ->whereDate('period', '>=', $start)
            ->whereDate('period', '<=', $end);

        $billed = (float) $thisMonth()->sum('amount');
        $collected = (float) $thisMonth()->sum('paid_amount');

        $unpaid = fn () => Charge::query()->where('status', '!=', Charge::STATUS_PAID);

        $due = (float) $unpaid()->sum(DB::raw('amount - paid_amount'));
        $overdue = $unpaid()->whereDate('due_on', '<', today())->count();

        $units = Unit::count();
        $occupied = Unit::where('status', Unit::STATUS_OCCUPIED)->count();

        $stats = [
            Stat::make('এই মাসের বিল', '৳ ' . number_format($billed))
                ->description('আদায় হয়েছে: ৳ ' . number_format($collected))
                ->color('success'),
            Stat::make('মোট বকেয়া', '৳ ' . number_format($due))
                ->description($overdue . 'টি বিলের মেয়াদ পেরিয়েছে')
                ->color($overdue > 0 ? 'danger' : 'success'),
            Stat::make('ইউনিট', "{$occupied} / {$units}")
                ->description('ভাড়া দেওয়া / মোট'),
        ];

        $claims = PaymentClaim::query()->where('status', PaymentClaim::STATUS_PENDING)->count();

        if ($claims > 0) {
            $stats[] = Stat::make('যাচাইয়ের অপেক্ষায়', (string) $claims)
                ->description('ভাড়াটিয়ার জানানো পেমেন্ট')
                ->color('warning');
        }

        if (auth()->user()->hasFeature('sms_reminders')) {
            $stats[] = Stat::make('SMS ক্রেডিট', (string) (int) auth()->user()->sms_credits)
                ->description('শেষ হলে অ্যাডমিনের কাছ থেকে নিন')
                ->color(auth()->user()->sms_credits > 0 ? 'success' : 'danger');
        }

        $endsAt = auth()->user()->subscription_ends_at;

        if ($endsAt) {
            $days = (int) round(now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false));

            $stats[] = Stat::make('সাবস্ক্রিপশন', $days >= 0 ? $endsAt->format('d M Y') : 'মেয়াদ শেষ')
                ->description($days >= 0 ? "{$days} দিন বাকি" : 'নবায়নের জন্য যোগাযোগ করুন')
                ->color($days < 0 ? 'danger' : ($days <= 7 ? 'warning' : 'success'));
        }

        return $stats;
    }
}
