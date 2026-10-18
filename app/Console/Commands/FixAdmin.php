<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class FixAdmin extends Command
{
    protected $signature = 'app:fix-admin
        {email : Admin email (created if it does not exist)}
        {--password= : New password. Omit it to be asked (typing is hidden)}';

    protected $description = 'Create/repair a super admin: sets password, is_admin, active, no expiry';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! $this->confirm("No user with {$email}. Create a new super admin?", true)) {
                return self::FAILURE;
            }

            $user = new User();
            $user->forceFill(['name' => $this->ask('Name', 'Admin'), 'email' => $email]);
        }

        $password = $this->option('password') ?: $this->secret('New password (min 8 characters)');

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'is_admin' => true,
            'is_active' => true,
            'subscription_ends_at' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $fresh = User::find($user->id);

        $this->table(['Email', 'Super admin', 'Active', 'Password check'], [[
            $fresh->email,
            $fresh->is_admin ? 'yes' : 'NO',
            $fresh->is_active ? 'yes' : 'NO',
            Hash::check($password, $fresh->password) ? 'OK' : 'FAILED',
        ]]);

        $this->info('Done. Now log in at /admin with this email and password.');

        return self::SUCCESS;
    }
}
