<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Lease;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyCharges extends Command
{
    protected $signature = 'rent:generate-charges {--month= : Month in YYYY-MM format (default: current month)}';

    protected $description = 'Create the monthly rent charge for every active lease (safe to run more than once)';

    public function handle(): int
    {
        $monthOption = $this->option('month');

        try {
            $month = $monthOption
                ? Carbon::createFromFormat('!Y-m', $monthOption)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid --month value. Use YYYY-MM, e.g. 2026-11');

            return self::FAILURE;
        }

        $monthEnd = $month->copy()->endOfMonth();
        $created = 0;

        Lease::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $monthEnd)
            ->where(function ($query) use ($month) {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month);
            })
            ->chunkById(200, function ($leases) use ($month, &$created) {
                foreach ($leases as $lease) {
                    $dueDay = min(max((int) $lease->due_day, 1), $month->daysInMonth);

                    $exists = Charge::withoutGlobalScopes()
                        ->where('lease_id', $lease->id)
                        ->where('type', 'rent')
                        ->whereDate('period', $month->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    Charge::unguarded(fn () => Charge::create([
                        'user_id' => $lease->user_id,
                        'lease_id' => $lease->id,
                        'period' => $month->toDateString(),
                        'type' => 'rent',
                        'amount' => $lease->monthly_rent,
                        'due_on' => $month->copy()->day($dueDay)->toDateString(),
                    ]));

                    $created++;
                }
            });

        $this->info("Rent charges created for {$month->format('F Y')}: {$created}");

        return self::SUCCESS;
    }
}
