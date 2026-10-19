<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToOwner;

    public const CATEGORIES = [
        'repair' => 'মেরামত',
        'caretaker' => 'কেয়ারটেকার বেতন',
        'utility' => 'ইউটিলিটি',
        'tax' => 'ট্যাক্স / হোল্ডিং',
        'other' => 'অন্যান্য',
    ];

    protected $fillable = ['property_id', 'category', 'amount', 'spent_on', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
    ];

    public function ownedForeignKeys(): array
    {
        return ['property_id' => Property::class];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
