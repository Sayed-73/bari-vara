<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    use BelongsToOwner;

    public const TYPES = [
        'rent' => 'Rent',
        'gas' => 'Gas',
        'water' => 'Water',
        'electricity' => 'Electricity',
        'service' => 'Service charge',
        'other' => 'Other',
    ];

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    protected $fillable = ['lease_id', 'period', 'type', 'description', 'amount', 'due_on'];

    protected $casts = [
        'period' => 'date',
        'due_on' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // A landlord may edit the amount at any time; status always follows the numbers.
        static::saving(fn (Charge $charge) => $charge->syncStatus());
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dueAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function syncStatus(): void
    {
        $paid = round((float) $this->paid_amount, 2);
        $total = round((float) $this->amount, 2);

        $this->status = $paid <= 0
            ? self::STATUS_UNPAID
            : ($paid >= $total ? self::STATUS_PAID : self::STATUS_PARTIAL);
    }

    /** Recalculate paid_amount and status from the payments table (single source of truth). */
    public function refreshStatus(): void
    {
        $this->paid_amount = round((float) Payment::withoutGlobalScopes()
            ->where('charge_id', $this->id)
            ->sum('amount'), 2);

        $this->syncStatus();
        $this->saveQuietly();
    }
}
