<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Support\OwnerContext;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Charge extends Model
{
    use BelongsToOwner;

    public const TYPES = [
        'rent' => 'ভাড়া (Rent)',
        'gas' => 'গ্যাস (Gas)',
        'water' => 'পানি (Water)',
        'electricity' => 'বিদ্যুৎ (Electricity)',
        'service' => 'সার্ভিস চার্জ',
        'other' => 'অন্যান্য',
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
        static::creating(function (Charge $charge) {
            $charge->pay_token ??= static::newToken();
        });

        static::saving(function (Charge $charge) {
            // A bill always belongs to a whole month (1st of the month).
            if ($charge->period) {
                $charge->period = Carbon::parse($charge->period)->startOfMonth();
            }

            // No due date given -> use the lease's due day.
            if (empty($charge->due_on) && $charge->lease_id && $charge->period) {
                $lease = Lease::withoutGlobalScopes()->find($charge->lease_id);
                $day = min(max((int) ($lease?->due_day ?? 5), 1), $charge->period->daysInMonth);
                $charge->due_on = $charge->period->copy()->day($day);
            }

            // A landlord may edit the amount at any time; status always follows the numbers.
            $charge->syncStatus();
        });
    }

    public static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (static::withoutGlobalScopes()->where('pay_token', $token)->exists());

        return $token;
    }

    /** Public link the tenant opens to see the bill and pay (created on first use for old bills). */
    public function payUrl(): string
    {
        if (! $this->pay_token) {
            $this->pay_token = static::newToken();
            $this->saveQuietly();
        }

        return route('bill.show', $this->pay_token);
    }

    /** wa.me link with a ready message + the bill link; opens WhatsApp with the tenant's number filled in. */
    public function whatsappUrl(): string
    {
        return OwnerContext::run(function () {
            $this->loadMissing('lease.tenant');

            $tenant = $this->lease?->tenant;
            $owner = User::find($this->user_id);
            $type = self::TYPES[$this->type] ?? $this->type;

            $text = "প্রিয় {$tenant?->name}, {$this->period->format('M Y')} মাসের {$type} ৳"
                . number_format($this->dueAmount())
                . ' বাকি। বিল দেখুন ও পেমেন্ট করুন: ' . $this->payUrl()
                . " - {$owner?->name}";

            $number = $tenant ? Phone::normalize($tenant->phone) : '';
            $phone = Phone::isValid($number) ? '88' . $number : '';

            return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
        });
    }

    public function ownedForeignKeys(): array
    {
        return ['lease_id' => Lease::class];
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
