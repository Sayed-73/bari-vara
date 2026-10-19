<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Lease extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'unit_id', 'tenant_id', 'starts_on', 'ends_on',
        'monthly_rent', 'due_day', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'monthly_rent' => 'decimal:2',
        'due_day' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // One active lease per unit.
        static::saving(function (Lease $lease) {
            if (! ($lease->is_active ?? true)) {
                return;
            }

            $clash = static::withoutGlobalScopes()
                ->where('unit_id', $lease->unit_id)
                ->where('is_active', true)
                ->when($lease->exists, fn ($query) => $query->whereKeyNot($lease->getKey()))
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'unit_id' => 'এই ইউনিটে আগে থেকেই একটি চালু চুক্তি আছে।',
                ]);
            }
        });

        static::saved(fn (Lease $lease) => $lease->syncUnitStatus());
        static::deleted(fn (Lease $lease) => $lease->syncUnitStatus());
    }

    public function ownedForeignKeys(): array
    {
        return ['unit_id' => Unit::class, 'tenant_id' => Tenant::class];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function label(): string
    {
        return "{$this->unit?->property?->name} – {$this->unit?->name} – {$this->tenant?->name}";
    }

    /** id => "Building – Unit – Tenant" for all active leases of the logged-in landlord. */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->with(['unit.property', 'tenant'])
            ->get()
            ->mapWithKeys(fn (Lease $lease) => [$lease->id => $lease->label()])
            ->all();
    }

    /** Keep units.status (vacant/occupied) in sync with active leases. */
    public function syncUnitStatus(): void
    {
        $occupied = static::withoutGlobalScopes()
            ->where('unit_id', $this->unit_id)
            ->where('is_active', true)
            ->exists();

        Unit::withoutGlobalScopes()
            ->whereKey($this->unit_id)
            ->update(['status' => $occupied ? Unit::STATUS_OCCUPIED : Unit::STATUS_VACANT]);
    }
}
