<?php

namespace Tests\Feature;

use App\Filament\Owner\Resources\Feedback\FeedbackResource as OwnerFeedbackResource;
use App\Filament\Resources\Feedback\FeedbackResource as AdminFeedbackResource;
use App\Models\Charge;
use App\Models\FeedbackMessage;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Step6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'sms.driver' => 'log']);

        // Works even before routes/step4.php is required from routes/web.php.
        if (! Route::has('report.month')) {
            Route::middleware('web')->group(base_path('routes/step4.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    private function landlord(array $overrides = [], array $features = ['reports', 'expense_tracking', 'sms_reminders', 'utility_bills', 'multiple_properties', 'receipt_pdf']): User
    {
        $plan = Plan::create([
            'name' => 'Plan ' . uniqid(),
            'monthly_fee' => 600,
            'max_units' => 30,
            'features' => $features,
        ]);

        return User::factory()->create(array_merge(['plan_id' => $plan->id, 'sms_credits' => 5], $overrides));
    }

    private function makeBill(User $user, string $dueOn, float $amount = 12000, string $tenantName = 'Karim'): Charge
    {
        $this->actingAs($user);

        $property = Property::create(['name' => 'Rahman Villa']);
        $unit = $property->units()->create(['name' => '2A', 'base_rent' => $amount]);
        $tenant = Tenant::create(['name' => $tenantName, 'phone' => '01711111111']);
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

    public function test_feedback_goes_from_new_to_answered_to_resolved(): void
    {
        config(['support.email' => 'support@example.test']);

        $user = $this->landlord();
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(FeedbackService::class);

        $message = $service->submit($user, ['type' => 'help', 'subject' => 'Hi', 'message' => 'I need help']);
        $this->assertSame(FeedbackMessage::STATUS_NEW, $message->status);

        $service->reply($message, 'Sure, here you go', $admin);
        $message->refresh();

        $this->assertSame(FeedbackMessage::STATUS_ANSWERED, $message->status);
        $this->assertSame('Sure, here you go', $message->admin_reply);
        $this->assertSame($admin->id, (int) $message->replied_by);

        $service->resolve($message);
        $this->assertSame(FeedbackMessage::STATUS_RESOLVED, $message->fresh()->status);
    }

    public function test_feedback_pages_load_and_badges_are_private_to_each_side(): void
    {
        $this->assertNotEmpty(OwnerFeedbackResource::getPages());
        $this->assertNotEmpty(AdminFeedbackResource::getPages());
        $this->assertNotNull(OwnerFeedbackResource::getNavigationIcon());
        $this->assertNotNull(AdminFeedbackResource::getNavigationIcon());

        $landlord = $this->landlord();
        $other = $this->landlord();
        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(FeedbackService::class);

        $message = $service->submit($landlord, ['type' => 'bug', 'message' => 'Something broke']);

        $this->assertSame('1', AdminFeedbackResource::getNavigationBadge());

        $this->actingAs($landlord);
        $this->assertNull(OwnerFeedbackResource::getNavigationBadge());

        $service->reply($message, 'Fixed', $admin);

        $this->assertNull(AdminFeedbackResource::getNavigationBadge());
        $this->assertSame('1', OwnerFeedbackResource::getNavigationBadge());

        $this->actingAs($other);
        $this->assertNull(OwnerFeedbackResource::getNavigationBadge());
    }

    public function test_monthly_report_shows_the_numbers_and_respects_login_and_feature(): void
    {
        $this->get(route('report.month'))->assertRedirect('/owner/login');

        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));

        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-05');
        $charge->payments()->create(['amount' => 5000, 'paid_at' => now()]);

        $this->get(route('report.month', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Rahman Villa')
            ->assertSee('12,000')
            ->assertSee('5,000')
            ->assertSee('7,000');

        $noReports = $this->landlord([], ['utility_bills']);
        $this->actingAs($noReports)->get(route('report.month'))->assertForbidden();
    }

    public function test_csv_download_is_safe_against_formula_injection(): void
    {
        $user = $this->landlord();
        $this->makeBill($user, '2026-10-05', 12000, '=cmd|x');

        $response = $this->get(route('report.month', ['month' => '2026-10', 'format' => 'csv']));
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Building,Unit,Tenant', $csv);
        $this->assertStringContainsString("'=cmd|x", $csv);
    }

    public function test_switched_off_landlord_sees_the_friendly_403_page(): void
    {
        $suspended = $this->landlord(['is_active' => false]);

        $this->actingAs($suspended)
            ->get('/owner')
            ->assertForbidden()
            ->assertSee('আপনার অ্যাকাউন্ট এখন চালু নেই');
    }

    public function test_home_and_legal_pages(): void
    {
        config(['support.email' => 'help@example.test']);
        Plan::create(['name' => 'Gold', 'monthly_fee' => 700, 'max_units' => 50, 'features' => []]);

        $this->get('/')->assertOk()->assertSee('বিনামূল্যে শুরু করুন')->assertSee('Gold');
        $this->get('/privacy')->assertOk()->assertSee('গোপনীয়তা নীতি')->assertSee('help@example.test');
        $this->get('/terms')->assertOk()->assertSee('ব্যবহারের শর্তাবলী');
        $this->get('/account-deletion')->assertOk()->assertSee('অ্যাকাউন্ট ও ডাটা মুছে ফেলার অনুরোধ');
    }

    public function test_account_deletion_removes_only_that_landlords_data(): void
    {
        $a = $this->landlord();
        $billA = $this->makeBill($a, '2026-10-05');
        $billA->payments()->create(['amount' => 1000, 'paid_at' => now()]);

        $b = $this->landlord();
        $this->makeBill($b, '2026-10-05');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->artisan('app:delete-landlord', ['email' => $admin->email, '--force' => true])->assertFailed();
        $this->assertNotNull(User::find($admin->id));

        $this->artisan('app:delete-landlord', ['email' => $a->email, '--force' => true])->assertSuccessful();

        $this->assertNull(User::find($a->id));
        $this->assertSame(0, Charge::withoutGlobalScopes()->where('user_id', $a->id)->count());
        $this->assertSame(0, Payment::withoutGlobalScopes()->where('user_id', $a->id)->count());
        $this->assertSame(0, Property::withoutGlobalScopes()->where('user_id', $a->id)->count());

        $this->assertSame(1, Charge::withoutGlobalScopes()->where('user_id', $b->id)->count());
    }

    public function test_sms_and_mail_test_commands(): void
    {
        $this->artisan('app:test-sms', ['phone' => '01711111111'])->assertSuccessful();
        $this->artisan('app:test-sms', ['phone' => '123'])->assertFailed();

        config([
            'sms.driver' => 'bulksmsbd',
            'sms.drivers.bulksmsbd' => ['base_url' => 'http://bulksmsbd.net', 'api_key' => 'k', 'sender_id' => 's'],
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::sequence()->push(['response_code' => 202])->push(['response_code' => 1001]),
        ]);

        $this->artisan('app:test-sms', ['phone' => '01711111111'])->assertSuccessful();
        $this->artisan('app:test-sms', ['phone' => '01711111111'])->assertFailed();

        $this->artisan('app:test-mail', ['to' => 'someone@example.test'])->assertSuccessful();
    }

    public function test_receipts_follow_the_receipt_feature_switch(): void
    {
        $user = $this->landlord([], ['utility_bills']); // plan without receipts
        $charge = $this->makeBill($user, '2026-10-05');
        $payment = $charge->payments()->create(['amount' => 1000, 'paid_at' => now()]);

        $this->get(route('receipt.show', $payment->receipt_token))->assertNotFound();
        $this->get(route('bill.show', $charge->pay_token))->assertOk()->assertDontSee($payment->receipt_token);

        $user->update(['feature_overrides' => ['receipt_pdf' => 'on']]);

        $this->get(route('receipt.show', $payment->receipt_token))->assertOk();
        $this->get(route('bill.show', $charge->pay_token))->assertOk()->assertSee($payment->receipt_token);
    }

    public function test_feature_catalog_has_no_dead_switches(): void
    {
        $this->assertArrayNotHasKey('tenant_portal', config('features.catalog'));
        $this->assertArrayHasKey('reports', config('features.catalog'));
    }

    public function test_pwa_files_are_complete(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('/owner', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertGreaterThanOrEqual(3, count($manifest['icons']));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertIsArray(json_decode(file_get_contents(public_path('.well-known/assetlinks.json')), true));
    }
}
