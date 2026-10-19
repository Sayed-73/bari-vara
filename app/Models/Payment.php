<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use BelongsToOwner;

    public const METHODS = [
        'cash' => 'নগদ (Cash)',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'rocket' => 'Rocket',
        'bank' => 'ব্যাংক (Bank)',
        'card' => 'কার্ড (Card)',
        'online' => 'অনলাইন (অন্যান্য)',
    ];

    protected $fillable = ['charge_id', 'amount', 'method', 'trx_id', 'paid_at', 'note', 'source', 'payment_attempt_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->receipt_token ??= static::newToken();
        });

        // Total payments can never exceed the bill. (Edit the bill amount first if needed.)
        static::saving(function (Payment $payment) {
            $charge = Charge::withoutGlobalScopes()->find($payment->charge_id);

            if (! $charge) {
                return;
            }

            $others = (float) static::withoutGlobalScopes()
                ->where('charge_id', $charge->id)
                ->when($payment->exists, fn ($query) => $query->whereKeyNot($payment->getKey()))
                ->sum('amount');

            if (round($others + (float) $payment->amount, 2) > round((float) $charge->amount, 2)) {
                throw ValidationException::withMessages([
                    'amount' => 'পেমেন্ট বিলের চেয়ে বেশি হতে পারবে না।',
                ]);
            }
        });

        $sync = fn (Payment $payment) => Charge::withoutGlobalScopes()
            ->find($payment->charge_id)
            ?->refreshStatus();

        static::saved($sync);
        static::deleted($sync);
    }

    public static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(14));
        } while (static::withoutGlobalScopes()->where('receipt_token', $token)->exists());

        return $token;
    }

    public function receiptUrl(): string
    {
        if (! $this->receipt_token) {
            $this->receipt_token = static::newToken();
            $this->saveQuietly();
        }

        return route('receipt.show', $this->receipt_token);
    }

    public function ownedForeignKeys(): array
    {
        return ['charge_id' => Charge::class];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
