<?php

namespace Tests\Feature;

use App\Filament\Owner\Resources\Charges\Pages\ManageCharges;
use App\Filament\Resources\Landlords\LandlordResource;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LandlordStats;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Step4bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Works even before routes/step4.php is required from routes/web.php.
        if (! Route::has('impersonate.start')) {
            Route::middleware('web')->group(base_path('routes/step4.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    private function landlord(array $overrides = []): User
    {
        $plan = Plan::create([
            'name' => 'Plan ' . uniqid(),
            'monthly_fee' => 600,
            'max_units' => 30,
            'features' => ['multiple_properties', 'sms_reminders', 'bkash_collection', 'utility_bills'],
        ]);

        return User::factory()->create(array_merge(['plan_id' => $plan->id, 'sms_credits' => 5], $overrides));
    }

    private function makeBill(User $user, string $dueOn, float $amount = 12000): Charge
    {
        $this->actingAs($user);

        $property = Property::create(['name' => 'Rahman Villa']);
        $unit = $property->units()->create(['name' => '2A', 'base_rent' => $amount]);
        $tenant = Tenant::create(['name' => 'Karim', 'phone' => '01711111111']);
        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'monthly_rent' => $amount,
            'due_day' => 5,
            'is_active' => true,
        ]);

        return Charge::create([
            'lease_id' => $lease->id,
            'type' => 'rent',
            'period' => '2026-10-01',
            'amount' => $amount,
            'due_on' => $dueOn,
        ]);
    }

    public function test_bills_page_has_one_tab_per_month_newest_first(): void
    {
        $user = $this->landlord();
        $october = $this->makeBill($user, '2026-10-05');

        Charge::create([
            'lease_id' => $october->lease_id,
            'type' => 'rent',
            'period' => '2026-09-01',
            'amount' => 12000,
            'due_on' => '2026-09-05',
        ]);

        $page = new ManageCharges();

        $this->assertSame(['2026-10', '2026-09', 'all'], array_keys($page->getTabs()));
        $this->assertSame('2026-10', $page->getDefaultActiveTab());
    }

    public function test_admin_sees_each_landlords_numbers(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-05');
        $charge->payments()->create(['amount' => 5000, 'paid_at' => now()]);

        $stats = LandlordStats::for($user);

        $this->assertSame(1, $stats['units']);
        $this->assertSame(1, $stats['occupied']);
        $this->assertSame(1, $stats['tenants']);
        $this->assertSame(12000.0, $stats['billed']);
        $this->assertSame(5000.0, $stats['collected']);
        $this->assertSame(7000.0, $stats['due']);
        $this->assertSame(1, $stats['overdue']);

        $this->assertNotEmpty(LandlordResource::getNavigationLabel());
        $this->assertNotEmpty(LandlordResource::getPages());
    }

    public function test_admin_can_open_a_landlord_panel_and_leave_again(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $landlord = $this->landlord(['is_active' => false]); // even a switched-off account

        $url = URL::temporarySignedRoute('impersonate.start', now()->addMinutes(5), ['user' => $landlord->id]);

        $this->actingAs($admin)->get($url)->assertRedirect('/owner');

        $this->assertAuthenticatedAs($landlord);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame(1, SubscriptionLog::where('user_id', $landlord->id)->where('admin_id', $admin->id)->count());
        $this->assertTrue($landlord->canAccessPanel(Panel::make()->id('owner')));

        $this->post(route('impersonate.leave'))->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
        $this->assertFalse($landlord->canAccessPanel(Panel::make()->id('owner')));
    }

    public function test_only_admins_can_open_landlord_panels_and_only_with_a_valid_signature(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $landlord = $this->landlord();
        $other = $this->landlord();

        $signed = fn (User $target) => URL::temporarySignedRoute(
            'impersonate.start',
            now()->addMinutes(5),
            ['user' => $target->id],
        );

        $this->actingAs($landlord)->get($signed($other))->assertForbidden();
        $this->actingAs($admin)->get(route('impersonate.start', ['user' => $other->id]))->assertForbidden();
        $this->actingAs($admin)->get($signed($admin))->assertNotFound();
        $this->actingAs($admin)->post(route('impersonate.leave'))->assertForbidden();
    }

    public function test_bill_page_shows_manual_instructions_even_when_online_payment_is_on(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-05');

        PaymentAccount::create([
            'store_id' => 'store1',
            'store_password' => 'pass1',
            'is_enabled' => true,
            'manual_instructions' => 'bKash Personal: 01799999999',
        ]);

        $this->get(route('bill.show', $charge->pay_token))
            ->assertOk()
            ->assertSee('অনলাইনে পেমেন্ট করুন')
            ->assertSee('অথবা সরাসরি পাঠান')
            ->assertSee('bKash Personal: 01799999999');
    }

    public function test_whatsapp_link_is_ready_with_the_tenant_number_and_bill_link(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-05');

        $url = $charge->whatsappUrl();

        $this->assertStringStartsWith('https://wa.me/8801711111111?text=', $url);
        $this->assertStringContainsString($charge->pay_token, rawurldecode($url));
    }
}
