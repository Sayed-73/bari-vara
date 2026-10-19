<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One online checkout try. Created and updated only by server code (no landlord scope on purpose). */
class PaymentAttempt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
    ];
}
