<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Unit extends Model
{
    use BelongsToOwner;

    public const STATUS_VACANT = 'vacant';
    public const STATUS_OCCUPIED = 'occupied';

    protected $fillable = ['property_id', 'name', 'base_rent', 'status'];

    protected $casts = ['base_rent' => 'decimal:2'];

    protected static function booted(): void
    {
        // Plan rule: unit limit (admin can raise it per landlord at any time).
        static::creating(function (Unit $unit) {
            $user = Auth::user();

            if ($user && ! $user->is_admin && ! $user->canAddUnit()) {
                throw ValidationException::withMessages([
                    'name' => 'আপনার প্ল্যানের ইউনিট লিমিট শেষ।',
                ]);
            }
        });
    }

    public function ownedForeignKeys(): array
    {
        return ['property_id' => Property::class];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('is_active', true);
    }
}
