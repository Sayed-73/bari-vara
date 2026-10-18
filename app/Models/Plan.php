<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'monthly_fee', 'max_units', 'features'];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'max_units' => 'integer',
        'features' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }
}
