<?php

namespace Tests\Feature;

use App\Filament\Owner\Resources\PaymentClaims\PaymentClaimResource;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentClaimService;
use App\Services\Payments\PaymentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Step5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Works even before routes/step4.php is required from routes/web.php.
        if (! Route::has('bill.claim')) {
            Route::middleware('web')->group(base_path('routes/step4.php'));
            app('router')->getRoutes()->refreshNameLookups();
        }
    }

    private function landlord(): User
    {
        $plan = Plan::create([
            'name' => 'Plan ' . uniqid(),
            'monthly_fee' => 600,
            'max_units' => 30,
            'features' => ['multiple_properties', 'sms_reminders', 'bkash_collection', 'utility_bills'],
        ]);

        return User::factory()->create(['plan_id' => $plan->id, 'sms_credits' => 5]);
    }

    private function makeBill(User $user, float $amount = 12000): Charge
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
            'due_on' => '2026-10-05',
        ]);
    }

    private function claimData(array $overrides = []): array
    {
        return array_merge(['amount' => 5000, 'method' => 'bkash', 'trx_id' => '9AB12CD34E'], $overrides);
    }

    public function test_a_claim_is_stored_cleaned_and_rules_are_enforced(): void
    {
        $charge = $this->makeBill($this->landlord());
        $service = app(PaymentClaimService::class);

        $claim = $service->submit($charge, $this->claimData([
            'trx_id' => ' 9ab12cd34e ',
            'sender_number' => '017-1111 1111',
        ]));

        $this->assertSame(PaymentClaim::STATUS_PENDING, $claim->status);
        $this->assertSame('9AB12CD34E', $claim->trx_id);
        $this->assertSame('01711111111', $claim->sender_number);

        $bad = [
            $this->claimData(['amount' => 999999, 'trx_id' => 'TRX00001']),            // more than the bill
            $this->claimData(['amount' => 0, 'trx_id' => 'TRX00002']),                  // zero
            $this->claimData(['method' => 'cash', 'trx_id' => 'TRX00003']),             // not a claimable method
            $this->claimData(['trx_id' => 'ab']),                                       // too short
            $this->claimData(['trx_id' => 'TRX$%^&00']),                                // bad characters
            $this->claimData(['trx_id' => '9ab12cd34e']),                               // same ID again
        ];

        foreach ($bad as $data) {
            try {
                $service->submit($charge, $data);
                $this->fail('Should have been rejected: ' . json_encode($data));
            } catch (PaymentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->assertSame(1, PaymentClaim::count());
    }

    public function test_at_most_three_unchecked_claims_per_bill(): void
    {
        $charge = $this->makeBill($this->landlord());
        $service = app(PaymentClaimService::class);

        foreach (['AAAA1', 'BBBB2', 'CCCC3'] as $trx) {
            $service->submit($charge, $this->claimData(['amount' => 100, 'trx_id' => $trx]));
        }

        $this->expectException(PaymentException::class);
        $service->submit($charge, $this->claimData(['amount' => 100, 'trx_id' => 'DDDD4']));
    }

    public function test_a_transaction_id_already_used_for_a_payment_is_refused(): void
    {
        $charge = $this->makeBill($this->landlord());
        $charge->payments()->create(['amount' => 1000, 'method' => 'bkash', 'trx_id' => 'bk-777', 'paid_at' => now()]);

        $this->expectException(PaymentException::class);
        app(PaymentClaimService::class)->submit($charge, $this->claimData(['trx_id' => 'BK-777']));
    }

    public function test_confirming_creates_the_payment_once(): void
    {
        $charge = $this->makeBill($this->landlord());
        $service = app(PaymentClaimService::class);

        $claim = $service->submit($charge, $this->claimData());
        $payment = $service->confirm($claim);

        $this->assertSame('bkash', $payment->method);
        $this->assertSame('9AB12CD34E', $payment->trx_id);
        $this->assertSame('claim', $payment->source);
        $this->assertSame(Charge::STATUS_PARTIAL, $charge->fresh()->status);
        $this->assertSame(PaymentClaim::STATUS_CONFIRMED, $claim->fresh()->status);
        $this->assertSame($payment->id, $claim->fresh()->payment_id);

        try {
            $service->confirm($claim);
            $this->fail('A claim must not be confirmed twice.');
        } catch (PaymentException $e) {
            $this->assertSame(1, Payment::count());
        }
    }

    public function test_confirm_can_correct_the_amount_but_never_exceed_the_bill(): void
    {
        $charge = $this->makeBill($this->landlord());
        $service = app(PaymentClaimService::class);

        $service->confirm($service->submit($charge, $this->claimData(['trx_id' => 'FIRST1'])));
        $second = $service->submit($charge, $this->claimData(['amount' => 7000, 'trx_id' => 'SECOND2']));

        try {
            $service->confirm($second, 8000); // only 7000 is due
            $this->fail('Amount above the due should be refused.');
        } catch (PaymentException $e) {
            $this->assertSame(PaymentClaim::STATUS_PENDING, $second->fresh()->status);
        }

        $service->confirm($second, 7000);

        $this->assertSame(Charge::STATUS_PAID, $charge->fresh()->status);
    }

    public function test_rejecting_keeps_the_reason_and_creates_no_payment(): void
    {
        $charge = $this->makeBill($this->landlord());
        $service = app(PaymentClaimService::class);

        $claim = $service->submit($charge, $this->claimData());
        $service->reject($claim, 'টাকা পাইনি');

        $this->assertSame(PaymentClaim::STATUS_REJECTED, $claim->fresh()->status);
        $this->assertSame('টাকা পাইনি', $claim->fresh()->review_note);
        $this->assertSame(0, Payment::count());

        $this->expectException(PaymentException::class);
        $service->confirm($claim);
    }

    public function test_tenant_form_on_the_bill_page(): void
    {
        $charge = $this->makeBill($this->landlord());
        $token = $charge->pay_token;

        $this->get(route('bill.show', $token))
            ->assertOk()
            ->assertSee('পেমেন্ট পাঠিয়েছেন? জানিয়ে দিন');

        $this->post(route('bill.claim', $token), $this->claimData(['method' => 'nagad', 'trx_id' => 'NG123456']))
            ->assertRedirect(route('bill.show', $token))
            ->assertSessionHas('status');

        $this->get(route('bill.show', $token))
            ->assertOk()
            ->assertSee('যাচাইয়ের অপেক্ষায়')
            ->assertSee('NG123456');

        $this->post(route('bill.claim', $token), $this->claimData(['trx_id' => 'NG123456']))
            ->assertRedirect(route('bill.show', $token))
            ->assertSessionHas('error');

        $this->post(route('bill.claim', $token), ['amount' => 100])
            ->assertSessionHasErrors(['method', 'trx_id']);

        $this->post(route('bill.claim', 'doesnotexist'), $this->claimData(['trx_id' => 'ZZZZ9999']))
            ->assertNotFound();
    }

    public function test_landlord_review_page_badge_and_privacy(): void
    {
        $this->assertNotEmpty(PaymentClaimResource::getPages());
        $this->assertNotNull(PaymentClaimResource::getNavigationIcon());

        $landlord = $this->landlord();
        $charge = $this->makeBill($landlord);
        $service = app(PaymentClaimService::class);

        $claim = $service->submit($charge, $this->claimData());

        $this->assertSame('1', PaymentClaimResource::getNavigationBadge());

        $other = $this->landlord();
        $this->actingAs($other);
        $this->assertSame(0, PaymentClaim::count());
        $this->assertNull(PaymentClaimResource::getNavigationBadge());

        $this->actingAs($landlord);
        $service->reject($claim);
        $this->assertNull(PaymentClaimResource::getNavigationBadge());
    }
}
