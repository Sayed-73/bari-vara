<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_off_and_expiry_control_access(): void
    {
        $landlord = User::factory()->create();
        $this->assertTrue($landlord->hasActiveSubscription());

        $landlord->update(['is_active' => false]);
        $this->assertFalse($landlord->fresh()->hasActiveSubscription());

        $landlord->update(['is_active' => true, 'subscription_ends_at' => now()->subDay()]);
        $this->assertFalse($landlord->fresh()->hasActiveSubscription());

        $landlord->update(['subscription_ends_at' => now()->addMonth()]);
        $this->assertTrue($landlord->fresh()->hasActiveSubscription());
    }

    public function test_custom_fee_and_unit_limit_override_the_plan(): void
    {
        $plan = Plan::create(['name' => 'Basic', 'monthly_fee' => 300, 'max_units' => 10, 'features' => []]);
        $landlord = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertSame(300.0, $landlord->monthlyFee());
        $this->assertSame(10, $landlord->unitLimit());

        $landlord->update(['custom_fee' => 250, 'custom_unit_limit' => 15]);

        $this->assertSame(250.0, $landlord->fresh()->monthlyFee());
        $this->assertSame(15, $landlord->fresh()->unitLimit());
    }

    public function test_feature_priority_override_then_plan_then_default(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'monthly_fee' => 600, 'features' => ['sms_reminders']]);
        $landlord = User::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($landlord->hasFeature('sms_reminders'));
        $this->assertFalse($landlord->hasFeature('reports'));

        $landlord->update(['feature_overrides' => ['sms_reminders' => 'off', 'reports' => 'on']]);
        $landlord = $landlord->fresh();

        $this->assertFalse($landlord->hasFeature('sms_reminders'));
        $this->assertTrue($landlord->hasFeature('reports'));

        $noPlan = User::factory()->create();
        $this->assertTrue($noPlan->hasFeature('utility_bills'));
        $this->assertFalse($noPlan->hasFeature('sms_reminders'));
    }

    public function test_changes_are_logged_with_the_admin_who_made_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $landlord = User::factory()->create();

        $this->actingAs($admin);
        $landlord->update(['is_active' => false]);

        $log = SubscriptionLog::where('user_id', $landlord->id)->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertArrayHasKey('is_active', $log->changes);
    }

    public function test_panel_access_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $landlord = User::factory()->create();
        $suspended = User::factory()->create(['is_active' => false]);

        $adminPanel = Panel::make()->id('admin');
        $ownerPanel = Panel::make()->id('owner');

        $this->assertTrue($admin->canAccessPanel($adminPanel));
        $this->assertFalse($landlord->canAccessPanel($adminPanel));
        $this->assertTrue($landlord->canAccessPanel($ownerPanel));
        $this->assertFalse($suspended->canAccessPanel($ownerPanel));
        $this->assertFalse($admin->canAccessPanel($ownerPanel));
    }

    public function test_landlord_can_edit_a_charge_amount_and_status_follows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create(['name' => 'Villa']);
        $unit = $property->units()->create(['name' => '1A', 'base_rent' => 10000]);
        $tenant = Tenant::create(['name' => 'Rahim', 'phone' => '01700000000']);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01', 'monthly_rent' => 10000, 'due_day' => 5, 'is_active' => true,
        ]);

        $this->artisan('rent:generate-charges --month=2026-10')->assertSuccessful();
        $charge = Charge::first();

        $charge->payments()->create(['amount' => 6000, 'paid_at' => now()]);
        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);

        // Landlord gives a discount: rent for this month becomes 6000.
        $charge->fresh()->update(['amount' => 6000]);
        $this->assertSame(Charge::STATUS_PAID, $charge->fresh()->status);

        // Landlord raises the monthly rent for future months only.
        $lease->update(['monthly_rent' => 11000]);
        $this->assertSame('6000.00', $charge->fresh()->amount);
    }
}
