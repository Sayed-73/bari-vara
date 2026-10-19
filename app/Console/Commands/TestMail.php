<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'app:test-mail {to : Address that should receive the test e-mail}';

    protected $description = 'Send one test e-mail with the current .env mail settings';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $config = config("mail.mailers.{$mailer}", []);

        $this->table(['Setting', 'Value'], [
            ['mailer', $mailer],
            ['host', $config['host'] ?? '-'],
            ['port', $config['port'] ?? '-'],
            ['username', ($config['username'] ?? null) ? 'set' : 'not set'],
            ['from', config('mail.from.address')],
        ]);

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log: nothing is really sent, the mail is written to storage/logs/laravel.log');
        }

        try {
            Mail::raw(
                'এটি একটি টেস্ট ইমেইল (' . config('app.name') . ')। এটি পেলে ইমেইল সেটআপ ঠিক আছে।',
                fn ($mail) => $mail->to($this->argument('to'))->subject('Test e-mail — ' . config('app.name')),
            );
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            $this->line('Check MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD and that MAIL_FROM_ADDRESS is a verified sender, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Test e-mail handed to the mail server. Check the inbox (and the spam folder).');

        return self::SUCCESS;
    }
}
