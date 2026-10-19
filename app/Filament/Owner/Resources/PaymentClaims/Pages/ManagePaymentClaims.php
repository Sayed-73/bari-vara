<?php

namespace App\Filament\Owner\Resources\PaymentClaims\Pages;

use App\Filament\Owner\Resources\PaymentClaims\PaymentClaimResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentClaims extends ManageRecords
{
    protected static string $resource = PaymentClaimResource::class;

    public function getSubheading(): ?string
    {
        return 'ভাড়াটিয়া বিল-লিংকে "পেমেন্ট পাঠিয়েছি" জানালে এখানে আসে। টাকা ঢুকেছে কিনা মিলিয়ে গ্রহণ করুন।';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
