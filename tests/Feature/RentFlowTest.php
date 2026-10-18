<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_landlord_cannot_see_another_landlords_data(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        Property::create(['name' => 'A House']);
        $this->assertSame(1, Property::count());

        $this->actingAs($b);
        $this->assertSame(0, Property::count());

        $this->actingAs($a);
        $this->assertSame(1, Property::count());
    }

    public function test_rent_charges_are_generated_once_and_payments_update_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create(['name' => 'Rahman Villa']);
        $unit = $property->units()->create(['name' => '2A', 'base_rent' => 12000]);
        $tenant = Tenant::create(['name' => 'Karim', 'phone' => '01711111111']);
        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'monthly_rent' => 12000,
            'due_day' => 5,
            'is_active' => true,
        ]);

        $this->assertSame(Unit::STATUS_OCCUPIED, $unit->fresh()->status);

        $this->artisan('rent:generate-charges --month=2026-10')->assertSuccessful();
        $this->artisan('rent:generate-charges --month=2026-10')->assertSuccessful();

        $this->assertSame(1, Charge::count());

        $charge = Charge::first();
        $this->assertSame('2026-10-05', $charge->due_on->toDateString());
        $this->assertSame(Charge::STATUS_UNPAID, $charge->status);

        Payment::create(['charge_id' => $charge->id, 'amount' => 5000, 'paid_at' => now()]);
        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);

        Payment::create(['charge_id' => $charge->id, 'amount' => 7000, 'method' => 'bkash', 'paid_at' => now()]);
        $this->assertSame(Charge::STATUS_PAID, $charge->fresh()->status);
    }

    public function test_invalid_month_option_fails_cleanly(): void
    {
        $this->artisan('rent:generate-charges --month=abc')->assertFailed();
    }
}
