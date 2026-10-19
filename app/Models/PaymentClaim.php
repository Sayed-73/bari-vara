<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "I have paid" note a tenant submits from the bill page; the landlord confirms or rejects it. */
class PaymentClaim extends Model
{
    use BelongsToOwner;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    // Written only by server code (the claim service), never from a landlord form.
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function ownedForeignKeys(): array
    {
        return ['charge_id' => Charge::class];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
