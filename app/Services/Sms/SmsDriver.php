<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /** @return array{0: bool, 1: string} [success, provider response] */
    public function send(string $phone, string $message): array;
}
