<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToOwner;

    public const CATEGORIES = [
        'repair' => 'Repair',
        'caretaker' => 'Caretaker salary',
        'utility' => 'Utility',
        'tax' => 'Tax / Holding',
        'other' => 'Other',
    ];

    protected $fillable = ['property_id', 'category', 'amount', 'spent_on', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
