<?php

namespace App\Filament\Owner\Resources\PaymentAccounts\Pages;

use App\Filament\Owner\Resources\PaymentAccounts\PaymentAccountResource;
use App\Models\PaymentAccount;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentAccounts extends ManageRecords
{
    protected static string $resource = PaymentAccountResource::class;

    public function getSubheading(): ?string
    {
        return 'এখানে যা লিখবেন ভাড়াটিয়া তার বিল-লিংকে দেখবে। লিংক পাঠাতে: মাসিক বিল → আরও → SMS / WhatsApp / বিল লিংক।';
    }

    protected function getHeaderActions(): array
    {
        // One settings record per landlord.
        if (PaymentAccount::query()->exists()) {
            return [];
        }

        return [CreateAction::make()->label('পেমেন্ট সেটিংস যোগ করো')];
    }
}
