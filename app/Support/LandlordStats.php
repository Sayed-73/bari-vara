<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Numbers for one landlord's dashboard, for the super admin (bypasses the per-landlord scope on purpose). */
final class LandlordStats
{
    public static function for(User $landlord): array
    {
        $units = fn () => Unit::withoutGlobalScopes()->where('user_id', $landlord->id);

        $month = fn () => Charge::withoutGlobalScopes()
            ->where('user_id', $landlord->id)
            ->whereDate('period', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('period', '<=', now()->endOfMonth()->toDateString());

        $unpaid = fn () => Charge::withoutGlobalScopes()
            ->where('user_id', $landlord->id)
            ->where('status', '!=', Charge::STATUS_PAID);

        return [
            'units' => $units()->count(),
            'occupied' => $units()->where('status', Unit::STATUS_OCCUPIED)->count(),
            'tenants' => Tenant::withoutGlobalScopes()->where('user_id', $landlord->id)->count(),
            'billed' => (float) $month()->sum('amount'),
            'collected' => (float) $month()->sum('paid_amount'),
            'due' => (float) $unpaid()->sum(DB::raw('amount - paid_amount')),
            'overdue' => $unpaid()->whereDate('due_on', '<', today())->count(),
        ];
    }
}
