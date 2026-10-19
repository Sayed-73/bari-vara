<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Property extends Model
{
    use BelongsToOwner;

    protected $fillable = ['name', 'address'];

    protected static function booted(): void
    {
        // Plan rule: more than one property needs the "multiple_properties" feature.
        static::creating(function (Property $property) {
            $user = Auth::user();

            if ($user && ! $user->is_admin && ! $user->canAddProperty()) {
                throw ValidationException::withMessages([
                    'name' => 'আপনার প্ল্যানে একাধিক বাড়ির সুবিধা নেই।',
                ]);
            }
        });
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
