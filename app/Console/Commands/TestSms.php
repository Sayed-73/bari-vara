<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsService;
use App\Support\Phone;
use Illuminate\Console\Command;

class TestSms extends Command
{
    protected $signature = 'app:test-sms {phone : 01XXXXXXXXX} {--message= : Custom text}';

    protected $description = 'Send one test SMS with the current SMS_DRIVER (does not use landlord credits)';

    public function handle(SmsService $sms): int
    {
        $phone = Phone::normalize((string) $this->argument('phone'));

        if (! Phone::isValid($phone)) {
            $this->error('Not a valid Bangladesh mobile number. Use 01XXXXXXXXX.');

            return self::FAILURE;
        }

        $driver = config('sms.driver');

        if ($driver === 'log') {
            $this->warn('SMS_DRIVER=log: nothing is really sent, the text goes to storage/logs/laravel.log');
        }

        $message = $this->option('message') ?: 'Test SMS from ' . config('app.name') . '. Setup OK.';

        try {
            [$ok, $response] = $sms->sendRaw($phone, $message);
        } catch (\Throwable $e) {
            [$ok, $response] = [false, $e->getMessage()];
        }

        $this->line("Driver: {$driver}");
        $this->line("Provider response: {$response}");

        if (! $ok) {
            $this->error('SMS was not accepted. Check the API key, sender ID, balance and phone number.');

            return self::FAILURE;
        }

        $this->info('SMS accepted by the provider.');

        return self::SUCCESS;
    }
}
