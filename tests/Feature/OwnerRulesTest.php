<?php

namespace Tests\Feature;

use App\Filament\Owner\Resources\Charges\ChargeResource;
use App\Filament\Owner\Resources\Expenses\ExpenseResource;
use App\Filament\Owner\Resources\Leases\LeaseResource;
use App\Filament\Owner\Resources\Payments\PaymentResource;
use App\Filament\Owner\Resources\Properties\PropertyResource;
use App\Filament\Owner\Resources\Tenants\TenantResource;
use App\Filament\Owner\Resources\Units\UnitResource;
use App\Filament\Owner\Widgets\RentOverview;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OwnerRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeLease(User $user, string $unitName = '1A', float $rent = 12000): Lease
    {
        $this->actingAs($user);

        $property = Property::first() ?? Property::create(['name' => 'Villa']);
        $unit = $property->units()->create(['name' => $unitName, 'base_rent' => $rent]);
        $tenant = Tenant::create(['name' => 'Tenant ' . $unitName, 'phone' => '01700000000']);

        return Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'monthly_rent' => $rent,
            'due_day' => 5,
            'is_active' => true,
        ]);
    }

    public function test_all_owner_panel_classes_load(): void
    {
        $resources = [
            PropertyResource::class, UnitResource::class, TenantResource::class, LeaseResource::class,
            ChargeResource::class, PaymentResource::class, ExpenseResource::class,
        ];

        foreach ($resources as $resource) {
            $this->assertNotEmpty($resource::getNavigationLabel());
            $this->assertNotNull($resource::getNavigationIcon());
            $this->assertNotEmpty($resource::getPages());
        }

        $this->assertTrue(class_exists(RentOverview::class));
        $this->assertTrue(class_exists(\App\Filament\Pages\Auth\Login::class));
        $this->assertTrue(class_exists(\App\Filament\Pages\Auth\Register::class));
    }

    public function test_cannot_reference_another_landlords_records(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $leaseA = $this->makeLease($a);

        $this->actingAs($b);
        $tenantB = Tenant::create(['name' => 'B tenant', 'phone' => '01800000000']);

        $this->expectException(AuthorizationException::class);

        Lease::create([
            'unit_id' => $leaseA->unit_id,
            'tenant_id' => $tenantB->id,
            'starts_on' => '2026-01-01',
            'monthly_rent' => 1000,
            'due_day' => 5,
            'is_active' => true,
        ]);
    }

    public function test_unit_limit_is_enforced_and_admin_can_raise_it(): void
    {
        $plan = Plan::create(['name' => 'Tiny', 'monthly_fee' => 0, 'max_units' => 2, 'features' => []]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $this->actingAs($user);

        $property = Property::create(['name' => 'P']);
        $property->units()->create(['name' => '1', 'base_rent' => 1000]);
        $property->units()->create(['name' => '2', 'base_rent' => 1000]);

        try {
            $property->units()->create(['name' => '3', 'base_rent' => 1000]);
            $this->fail('Third unit should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertTrue($user->canAddUnit() === false);
        }

        $user->update(['custom_unit_limit' => 3]);
        $property->units()->create(['name' => '3', 'base_rent' => 1000]);

        $this->assertSame(3, $property->units()->count());
    }

    public function test_second_property_needs_the_multiple_properties_feature(): void
    {
        $plan = Plan::create(['name' => 'Single', 'monthly_fee' => 0, 'features' => []]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $this->actingAs($user);

        Property::create(['name' => 'One']);

        try {
            Property::create(['name' => 'Two']);
            $this->fail('Second property should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertSame(1, Property::count());
        }

        $user->update(['feature_overrides' => ['multiple_properties' => 'on']]);
        Property::create(['name' => 'Two']);

        $this->assertSame(2, Property::count());
    }

    public function test_only_one_active_lease_per_unit(): void
    {
        $user = User::factory()->create();
        $lease = $this->makeLease($user);

        $tenant2 = Tenant::create(['name' => 'Second', 'phone' => '01900000000']);

        $this->expectException(ValidationException::class);

        Lease::create([
            'unit_id' => $lease->unit_id,
            'tenant_id' => $tenant2->id,
            'starts_on' => '2026-02-01',
            'monthly_rent' => 5000,
            'due_day' => 5,
            'is_active' => true,
        ]);
    }

    public function test_payments_cannot_exceed_the_bill_and_deleting_restores_status(): void
    {
        $user = User::factory()->create();
        $this->makeLease($user, '1A', 10000);

        $this->artisan('rent:generate-charges --month=2026-10')->assertSuccessful();
        $charge = Charge::first();

        $payment = $charge->payments()->create(['amount' => 4000, 'paid_at' => now()]);
        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);

        try {
            $charge->payments()->create(['amount' => 7000, 'paid_at' => now()]);
            $this->fail('Overpayment should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertSame('4000.00', $charge->fresh()->paid_amount);
        }

        $payment->delete();
        $this->assertSame(Charge::STATUS_UNPAID, $charge->fresh()->status);
    }

    public function test_bill_month_is_normalised_and_due_date_defaults_to_lease_day(): void
    {
        $user = User::factory()->create();
        $lease = $this->makeLease($user);

        $charge = Charge::create([
            'lease_id' => $lease->id,
            'type' => 'gas',
            'period' => '2026-11-17',
            'amount' => 500,
        ]);

        $this->assertSame('2026-11-01', $charge->period->toDateString());
        $this->assertSame('2026-11-05', $charge->due_on->toDateString());
    }

    public function test_expense_page_follows_the_feature_switch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(ExpenseResource::canAccess());

        $user->update(['feature_overrides' => ['expense_tracking' => 'on']]);
        $this->assertTrue(ExpenseResource::canAccess());

        $user->update(['feature_overrides' => ['expense_tracking' => 'off']]);
        $this->assertFalse(ExpenseResource::canAccess());
    }
}
