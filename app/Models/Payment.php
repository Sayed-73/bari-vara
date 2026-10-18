<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToOwner;

    public const METHODS = [
        'cash' => 'Cash',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'bank' => 'Bank',
    ];

    protected $fillable = ['charge_id', 'amount', 'method', 'trx_id', 'paid_at', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $sync = fn (Payment $payment) => Charge::withoutGlobalScopes()
            ->find($payment->charge_id)
            ?->refreshStatus();

        static::saved($sync);
        static::deleted($sync);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
