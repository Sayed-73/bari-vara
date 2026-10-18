<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use BelongsToOwner;

    protected $fillable = ['name', 'phone', 'nid', 'advance_amount', 'notes'];

    protected $casts = ['advance_amount' => 'decimal:2'];

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}
