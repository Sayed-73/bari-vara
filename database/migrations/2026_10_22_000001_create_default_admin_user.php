<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::firstOrNew(['email' => 'saidulislam0400@gmail.com']);

        $user->forceFill([
            'name' => 'Admin User',
            'email' => 'saidulislam0400@gmail.com',
            'password' => Hash::make('12345678'),
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
