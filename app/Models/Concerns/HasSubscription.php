<?php

namespace App\Models\Concerns;

use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubscriptionLog;
use App\Models\Unit;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * Subscription, feature flags and panel access for the User model.
 * NOTE: is_admin is intentionally NOT fillable (cannot be mass-assigned).
 */
trait HasSubscription
{
    public function initializeHasSubscription(): void
    {
        $this->mergeFillable([
            'phone', 'is_active', 'plan_id', 'custom_fee',
            'custom_unit_limit', 'subscription_ends_at', 'feature_overrides', 'sms_credits',
        ]);

        $this->mergeCasts([
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'custom_fee' => 'decimal:2',
            'custom_unit_limit' => 'integer',
            'sms_credits' => 'integer',
            'subscription_ends_at' => 'datetime',
            'feature_overrides' => 'array',
        ]);
    }

    protected static function bootHasSubscription(): void
    {
        // Every new landlord starts on the default plan unless one is chosen.
        static::creating(function ($user) {
            if (! $user->is_admin && empty($user->plan_id)) {
                $user->plan_id = Plan::where('name', config('features.default_plan', 'Free'))->value('id');
            }
        });

        // Audit trail: every change to money/on-off/limits/features is logged with who did it.
        static::updated(function ($user) {
            $fmt = fn ($value) => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;

            $changes = [];

            foreach (static::subscriptionTrackedFields() as $field) {
                if ($user->wasChanged($field)) {
                    $changes[$field] = [
                        'old' => $fmt($user->getOriginal($field)),
                        'new' => $fmt($user->getAttribute($field)),
                    ];
                }
            }

            if ($changes !== []) {
                SubscriptionLog::create([
                    'user_id' => $user->getKey(),
                    'admin_id' => Auth::id(),
                    'changes' => $changes,
                ]);
            }
        });
    }

    protected static function subscriptionTrackedFields(): array
    {
        return [
            'is_active', 'plan_id', 'custom_fee', 'custom_unit_limit',
            'subscription_ends_at', 'feature_overrides', 'sms_credits',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function paymentAccount(): HasOne
    {
        return $this->hasOne(PaymentAccount::class);
    }

    public function subscriptionLogs(): HasMany
    {
        return $this->hasMany(SubscriptionLog::class);
    }

    /** Admin switch ON and subscription not expired. */
    public function hasActiveSubscription(): bool
    {
        return ($this->is_active ?? true)
            && ($this->subscription_ends_at === null || $this->subscription_ends_at->isFuture());
    }

    /** Custom fee (set by admin) wins over the plan fee. */
    public function monthlyFee(): float
    {
        return (float) ($this->custom_fee ?? $this->plan?->monthly_fee ?? 0);
    }

    /** null = unlimited. */
    public function unitLimit(): ?int
    {
        if ($this->custom_unit_limit !== null) {
            return (int) $this->custom_unit_limit;
        }

        if ($this->plan) {
            return $this->plan->max_units;
        }

        return (int) config('features.no_plan_unit_limit', 3);
    }

    public function canAddUnit(): bool
    {
        $limit = $this->unitLimit();

        return $limit === null
            || Unit::withoutGlobalScopes()->where('user_id', $this->getKey())->count() < $limit;
    }

    /** First property is always allowed; more need the "multiple_properties" feature. */
    public function canAddProperty(): bool
    {
        return $this->hasFeature('multiple_properties')
            || ! Property::withoutGlobalScopes()->where('user_id', $this->getKey())->exists();
    }

    /** Priority: per-landlord override (on/off) > plan features > global defaults. */
    public function hasFeature(string $key): bool
    {
        $override = ($this->feature_overrides ?? [])[$key] ?? null;

        if ($override === 'on' || $override === true) {
            return true;
        }

        if ($override === 'off' || $override === false) {
            return false;
        }

        if ($this->plan) {
            return $this->plan->hasFeature($key);
        }

        return in_array($key, config('features.defaults', []), true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => (bool) $this->is_admin,
            default => ! $this->is_admin
                && ($this->hasActiveSubscription() || session()->has('impersonator_id')),
        };
    }
}
