<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : Email of an existing user}';

    protected $description = 'Give an existing user super-admin access to the /admin panel';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$user->email} is now a super admin.");

        return self::SUCCESS;
    }
}
