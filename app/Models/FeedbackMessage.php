<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Help / feedback / bug / feature / account-deletion request from a landlord to the platform admin. */
class FeedbackMessage extends Model
{
    public const TYPES = [
        'help' => 'সাহায্য চাই',
        'bug' => 'সমস্যা / বাগ',
        'feedback' => 'মতামত',
        'feature' => 'নতুন ফিচার চাই',
        'delete_account' => 'অ্যাকাউন্ট মুছে ফেলার অনুরোধ',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_NEW => 'পাঠানো হয়েছে',
        self::STATUS_ANSWERED => 'উত্তর এসেছে',
        self::STATUS_RESOLVED => 'সমাধান হয়েছে',
    ];

    // Written only by FeedbackService (no scope on purpose: the admin reads all of them).
    protected $guarded = [];

    protected $casts = ['replied_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
