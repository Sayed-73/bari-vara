<?php

namespace App\Models\Concerns;

use App\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy: every row belongs to one landlord (users.id) via user_id.
 *
 * - Logged-in user  -> sees only their own rows.
 * - Console/queue   -> no user, scope is skipped (commands must handle user_id explicitly).
 * - Anything else   -> sees nothing (fails closed, never leaks another landlord's data).
 *
 * Models can also list foreign keys that must point to the same landlord's data:
 *   public function ownedForeignKeys(): array { return ['unit_id' => Unit::class]; }
 * Saving a row that points to someone else's record is rejected.
 */
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            if (OwnerContext::bypassed()) {
                return;
            }

            $column = $builder->getModel()->getTable() . '.user_id';

            if (Auth::check()) {
                $builder->where($column, Auth::id());
            } elseif (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }
        });

        static::saving(function ($model) {
            if (OwnerContext::bypassed()) {
                return;
            }

            foreach ($model->ownedForeignKeys() as $column => $class) {
                $value = $model->getAttribute($column);

                if ($value === null || ($model->exists && ! $model->isDirty($column))) {
                    continue;
                }

                // Uses the owner scope, so another landlord's record is "not found".
                if (! $class::query()->whereKey($value)->exists()) {
                    throw new AuthorizationException('Invalid reference.');
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    /** @return array<string, class-string> foreign key column => related model class */
    public function ownedForeignKeys(): array
    {
        return [];
    }
}
