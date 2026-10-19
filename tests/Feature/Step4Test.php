<?php

namespace Tests\Feature;

use App\Filament\Owner\Resources\PaymentAccounts\PaymentAccountResource;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Step4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.driver' => 'log', 'sms.language' => 'en']);

        // Works even before routes/step4.php is required from routes/web.php.
        if (! Route::has('bill.show')) {
            Route::middleware('web')->group(base_path('routes/step4.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    private function landlord(array $overrides = [], array $features = ['multiple_properties', 'sms_reminders', 'bkash_collection', 'utility_bills', 'receipt_pdf']): User
    {
        $plan = Plan::create([
            'name' => 'Plan ' . uniqid(),
            'monthly_fee' => 600,
            'max_units' => 30,
            'features' => $features,
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

    private function enableGateway(): void
    {
        PaymentAccount::create([
            'store_id' => 'teststore',
            'store_password' => 'secret',
            'is_sandbox' => true,
            'is_enabled' => true,
        ]);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/v4/gw/abc',
                'sessionkey' => 'SK1',
            ]),
        ]);
    }

    private function fakeValidation(string $tranId, string $amount, string $status = 'VALID'): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => $status,
                'tran_id' => $tranId,
                'amount' => $amount,
                'card_type' => 'BKASH-BKash',
                'bank_tran_id' => 'BANK123',
            ]),
        ]);
    }

    public function test_sms_costs_one_credit_and_fails_safely(): void
    {
        $user = $this->landlord(['sms_credits' => 1]);
        $sms = app(SmsService::class);

        $this->assertSame('invalid_number', $sms->send($user, '123', 'bad')->status);
        $this->assertSame(1, $user->fresh()->sms_credits);

        $this->assertSame('sent', $sms->send($user, '01711111111', 'hello')->status);
        $this->assertSame(0, $user->fresh()->sms_credits);

        $this->assertSame('no_credit', $sms->send($user, '01711111111', 'again')->status);
    }

    public function test_bulksmsbd_driver_refunds_the_credit_when_the_gateway_fails(): void
    {
        config([
            'sms.driver' => 'bulksmsbd',
            'sms.drivers.bulksmsbd' => ['base_url' => 'http://bulksmsbd.net', 'api_key' => 'k', 'sender_id' => 's'],
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::sequence()->push(['response_code' => 202])->push(['response_code' => 1001]),
        ]);

        $user = $this->landlord(['sms_credits' => 2]);
        $sms = app(SmsService::class);

        $this->assertSame('sent', $sms->send($user, '01711111111', 'one')->status);
        $this->assertSame(1, $user->fresh()->sms_credits);

        $this->assertSame('failed', $sms->send($user, '01711111111', 'two')->status);
        $this->assertSame(1, $user->fresh()->sms_credits);
    }

    public function test_reminders_are_sent_once_per_stage_and_only_with_the_feature(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $user = $this->landlord(['sms_credits' => 10]);
        $this->makeBill($user, '2026-10-20');

        $noFeature = $this->landlord(['sms_credits' => 5], ['utility_bills']);
        $this->makeBill($noFeature, '2026-10-20');

        $this->artisan('rent:send-reminders')->assertSuccessful();
        $this->artisan('rent:send-reminders')->assertSuccessful();

        $this->assertSame(1, SmsLog::where('user_id', $user->id)->where('kind', 'r0')->count());
        $this->assertSame(9, $user->fresh()->sms_credits);
        $this->assertSame(0, SmsLog::where('user_id', $noFeature->id)->count());

        $this->travelTo(Carbon::parse('2026-10-25 09:00:00')); // 5 days late: not a reminder day
        $this->artisan('rent:send-reminders')->assertSuccessful();
        $this->assertSame(1, SmsLog::where('user_id', $user->id)->count());

        $this->travelTo(Carbon::parse('2026-10-23 09:00:00')); // 3 days late
        $this->artisan('rent:send-reminders')->assertSuccessful();
        $this->assertSame(1, SmsLog::where('user_id', $user->id)->where('kind', 'r3')->count());
    }

    public function test_online_payment_is_recorded_once_with_the_right_method(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $this->enableGateway();

        $service = app(OnlinePaymentService::class);
        $url = $service->start($charge, 5000);

        $this->assertSame('https://sandbox.sslcommerz.com/gwprocess/v4/gw/abc', $url);

        $attempt = PaymentAttempt::first();
        $this->assertSame('pending', $attempt->status);

        $this->fakeValidation($attempt->tran_id, '5000.00');

        $this->assertTrue($service->complete($attempt, 'VAL1'));
        $this->assertTrue($service->complete($attempt, 'VAL1')); // IPN + redirect: still one payment

        $this->assertSame(1, Payment::count());

        $payment = Payment::first();
        $this->assertSame('bkash', $payment->method);
        $this->assertSame('online', $payment->source);
        $this->assertSame('BANK123', $payment->trx_id);
        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);
    }

    public function test_online_payment_with_a_different_amount_is_rejected(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $this->enableGateway();

        $service = app(OnlinePaymentService::class);
        $service->start($charge, 5000);
        $attempt = PaymentAttempt::first();

        $this->fakeValidation($attempt->tran_id, '4000.00');

        $this->assertFalse($service->complete($attempt, 'VAL1'));
        $this->assertSame(0, Payment::count());
        $this->assertSame('failed', $attempt->fresh()->status);
    }

    public function test_money_received_after_the_bill_was_paid_in_cash_is_flagged_for_review(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $this->enableGateway();

        $service = app(OnlinePaymentService::class);
        $service->start($charge, 5000);
        $attempt = PaymentAttempt::first();

        $charge->payments()->create(['amount' => 12000, 'method' => 'cash', 'paid_at' => now()]);

        $this->fakeValidation($attempt->tran_id, '5000.00');

        $this->assertFalse($service->complete($attempt, 'VAL1'));
        $this->assertSame('needs_review', $attempt->fresh()->status);
        $this->assertSame(1, Payment::count());
    }

    public function test_public_bill_and_receipt_pages(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $payment = $charge->payments()->create(['amount' => 2000, 'method' => 'cash', 'paid_at' => now()]);

        $this->get(route('bill.show', $charge->pay_token))
            ->assertOk()
            ->assertSee('Rahman Villa')
            ->assertSee('10,000');

        $this->get(route('receipt.show', $payment->receipt_token))
            ->assertOk()
            ->assertSee('2,000');

        $this->get('/p/doesnotexist')->assertNotFound();
        $this->get('/r/doesnotexist')->assertNotFound();
    }

    public function test_pay_button_redirects_to_the_gateway_and_gateway_return_credits_the_bill(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $this->enableGateway();

        $this->post(route('bill.pay', $charge->pay_token), ['amount' => 999999])
            ->assertRedirect(route('bill.show', $charge->pay_token))
            ->assertSessionHas('error');

        $this->post(route('bill.pay', $charge->pay_token), ['amount' => 3000])
            ->assertRedirect('https://sandbox.sslcommerz.com/gwprocess/v4/gw/abc');

        $attempt = PaymentAttempt::first();
        $this->fakeValidation($attempt->tran_id, '3000.00');

        $this->get(route('pay.return', ['result' => 'success', 'tranId' => $attempt->tran_id, 'val_id' => 'VAL9']))
            ->assertRedirect(route('bill.show', $charge->pay_token));

        $this->assertSame(1, Payment::count());
        $this->assertSame('3000.00', Payment::first()->amount);
    }

    public function test_cancelled_checkout_marks_the_attempt_and_credits_nothing(): void
    {
        $user = $this->landlord();
        $charge = $this->makeBill($user, '2026-10-20');
        $this->enableGateway();

        app(OnlinePaymentService::class)->start($charge, 1000);
        $attempt = PaymentAttempt::first();

        $this->get(route('pay.return', ['result' => 'cancel', 'tranId' => $attempt->tran_id]))
            ->assertRedirect(route('bill.show', $charge->pay_token));

        $this->assertSame('cancelled', $attempt->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_payment_method_mapping_covers_bkash_nagad_cards_and_banks(): void
    {
        $service = app(OnlinePaymentService::class);

        $this->assertSame('bkash', $service->methodFromCardType('BKASH-BKash'));
        $this->assertSame('nagad', $service->methodFromCardType('NAGAD-Nagad'));
        $this->assertSame('card', $service->methodFromCardType('VISA-Dutch Bangla'));
        $this->assertSame('card', $service->methodFromCardType('MASTER-Brac Bank'));
        $this->assertSame('rocket', $service->methodFromCardType('DBBLMOBILEB-Dbbl Mobile Banking'));
        $this->assertSame('bank', $service->methodFromCardType('IB-City Bank'));
        $this->assertSame('online', $service->methodFromCardType('OKWALLET-OK Wallet'));

        foreach (['bkash', 'nagad', 'rocket', 'bank', 'card', 'cash'] as $method) {
            $this->assertArrayHasKey($method, Payment::METHODS);
        }
    }

    public function test_payment_settings_page_loads_and_credentials_are_encrypted(): void
    {
        $this->assertNotEmpty(PaymentAccountResource::getPages());
        $this->assertNotNull(PaymentAccountResource::getNavigationIcon());

        $user = $this->landlord();
        $this->actingAs($user);

        $account = PaymentAccount::create(['store_id' => 'store123', 'store_password' => 'topsecret', 'is_enabled' => true]);

        $raw = \DB::table('payment_accounts')->where('id', $account->id)->first();

        $this->assertNotSame('topsecret', $raw->store_password);
        $this->assertSame('topsecret', $account->fresh()->store_password);
        $this->assertArrayNotHasKey('store_password', $account->fresh()->toArray());
    }
}
