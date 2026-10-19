<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;

/** A landlord's own SSLCommerz store (money goes straight to the landlord) + manual payment instructions. */
class PaymentAccount extends Model
{
    use BelongsToOwner;

    protected $fillable = ['store_id', 'store_password', 'is_sandbox', 'is_enabled', 'manual_instructions'];

    protected $hidden = ['store_password'];

    protected $casts = [
        'store_id' => 'encrypted',
        'store_password' => 'encrypted',
        'is_sandbox' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function isReady(): bool
    {
        return $this->is_enabled && filled($this->store_id) && filled($this->store_password);
    }
}
