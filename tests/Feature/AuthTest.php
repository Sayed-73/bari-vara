<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_numbers_are_normalised(): void
    {
        $this->assertSame('01712345678', Phone::normalize('+880 1712-345678'));
        $this->assertSame('01712345678', Phone::normalize('8801712345678'));
        $this->assertSame('01712345678', Phone::normalize('017 1234 5678'));

        $this->assertTrue(Phone::isValid('01712345678'));
        $this->assertFalse(Phone::isValid('01212345678'));
        $this->assertFalse(Phone::isValid('1712345678'));
    }

    public function test_new_landlords_get_the_default_plan_but_admins_do_not(): void
    {
        $free = Plan::create(['name' => 'Free', 'monthly_fee' => 0, 'max_units' => 3, 'features' => []]);

        $landlord = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame($free->id, $landlord->fresh()->plan_id);
        $this->assertNull($admin->fresh()->plan_id);
    }

    public function test_fix_admin_command_repairs_login(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'is_active' => false,
            'subscription_ends_at' => now()->subYear(),
        ]);

        $this->artisan('app:fix-admin', ['email' => 'owner@example.test', '--password' => 'new-secret-123'])
            ->assertSuccessful();

        $fresh = $user->fresh();

        $this->assertTrue($fresh->is_admin);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->subscription_ends_at);
        $this->assertTrue(Hash::check('new-secret-123', $fresh->password));
    }

    public function test_fix_admin_rejects_short_passwords(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);

        $this->artisan('app:fix-admin', ['email' => 'owner@example.test', '--password' => 'short'])
            ->assertFailed();
    }
}
