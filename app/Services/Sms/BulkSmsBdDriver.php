<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/** bulksmsbd.net: success when the response_code is 202. */
class BulkSmsBdDriver implements SmsDriver
{
    public function send(string $phone, string $message): array
    {
        $config = config('sms.drivers.bulksmsbd');

        $response = Http::asForm()
            ->timeout(15)
            ->post(rtrim((string) $config['base_url'], '/') . '/api/smsapi', [
                'api_key' => $config['api_key'],
                'senderid' => $config['sender_id'],
                'number' => '88' . $phone,
                'message' => $message,
            ]);

        return [(int) $response->json('response_code') === 202, $response->body()];
    }
}
