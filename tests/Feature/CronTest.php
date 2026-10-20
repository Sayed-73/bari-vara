<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CronTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = str_repeat('k', 32);
        config(['cron.token' => $this->token]);

        // Works even before routes/step4.php is required from routes/web.php.
        if (! Route::has('cron.run')) {
            Route::middleware('web')->group(base_path('routes/step4.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    public function test_wrong_token_and_weak_tokens_are_refused(): void
    {
        $this->get('/cron/wrong-token')->assertNotFound();

        config(['cron.token' => 'short']);
        $this->get('/cron/short')->assertNotFound();

        config(['cron.token' => '']);
        $this->get('/cron/anything')->assertNotFound();
    }

    public function test_valid_token_creates_this_months_rent_once(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create(['name' => 'Villa']);
        $unit = $property->units()->create(['name' => '1A', 'base_rent' => 9000]);
        $tenant = Tenant::create(['name' => 'Karim', 'phone' => '01711111111']);
        Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'monthly_rent' => 9000,
            'due_day' => 5,
            'is_active' => true,
        ]);

        $this->get('/cron/' . $this->token)->assertOk()->assertJson(['ok' => true, 'reminders_checked' => true]);
        $this->get('/cron/' . $this->token)->assertOk();

        $this->assertSame(1, Charge::withoutGlobalScopes()->count());
    }

    public function test_reminders_are_only_checked_in_the_daytime(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 03:00:00'));

        $this->get('/cron/' . $this->token)->assertOk()->assertJson(['reminders_checked' => false]);
    }
}
