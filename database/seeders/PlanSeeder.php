<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Free', 'monthly_fee' => 0, 'max_units' => 3, 'features' => ['utility_bills']],
            ['name' => 'Basic', 'monthly_fee' => 300, 'max_units' => 10, 'features' => ['utility_bills', 'expense_tracking', 'receipt_pdf']],
            ['name' => 'Pro', 'monthly_fee' => 600, 'max_units' => 30, 'features' => ['utility_bills', 'expense_tracking', 'receipt_pdf', 'multiple_properties', 'reports', 'sms_reminders']],
            ['name' => 'Premium', 'monthly_fee' => 1000, 'max_units' => null, 'features' => array_keys(config('features.catalog'))],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
