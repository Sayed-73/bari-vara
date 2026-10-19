<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Development driver: nothing is sent, the text goes to storage/logs/laravel.log */
class LogDriver implements SmsDriver
{
    public function send(string $phone, string $message): array
    {
        Log::info("SMS to {$phone}: {$message}");

        return [true, 'logged'];
    }
}
