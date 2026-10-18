<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy: every row belongs to one landlord (users.id) via user_id.
 *
 * - Logged-in user  -> sees only their own rows.
 * - Console/queue   -> no user, scope is skipped (commands must handle user_id explicitly).
 * - Anything else   -> sees nothing (fails closed, never leaks another landlord's data).
 */
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            $column = $builder->getModel()->getTable() . '.user_id';

            if (Auth::check()) {
                $builder->where($column, Auth::id());
            } elseif (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }
}
