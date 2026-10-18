<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        static::saved(fn (Lease $lease) => $lease->syncUnitStatus());
        static::deleted(fn (Lease $lease) => $lease->syncUnitStatus());
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
