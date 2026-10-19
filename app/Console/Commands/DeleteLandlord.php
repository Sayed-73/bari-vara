<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LandlordStats;
use Illuminate\Console\Command;

class DeleteLandlord extends Command
{
    protected $signature = 'app:delete-landlord {email} {--force : Do not ask for confirmation}';

    protected $description = 'Permanently delete a landlord and ALL of his data (account-deletion requests)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that e-mail.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->error('Super admin accounts cannot be deleted with this command.');

            return self::FAILURE;
        }

        $stats = LandlordStats::for($user);

        $this->table(['Landlord', 'Units', 'Tenants', 'Open due (BDT)'], [[
            $user->name . ' <' . $user->email . '>',
            $stats['units'],
            $stats['tenants'],
            number_format($stats['due']),
        ]]);

        if (! $this->option('force') && ! $this->confirm('This permanently deletes the account and every property, tenant, bill, payment and message. Continue?')) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $user->delete(); // database cascades remove everything that belongs to him

        $this->info('Deleted.');

        return self::SUCCESS;
    }
}
