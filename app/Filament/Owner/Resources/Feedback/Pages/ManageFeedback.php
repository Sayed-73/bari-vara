<?php

namespace App\Filament\Owner\Resources\Feedback\Pages;

use App\Filament\Owner\Resources\Feedback\FeedbackResource;
use App\Services\FeedbackService;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ManageFeedback extends ManageRecords
{
    protected static string $resource = FeedbackResource::class;

    public function getSubheading(): ?string
    {
        $parts = [];

        if ($phone = config('support.phone')) {
            $parts[] = 'ফোন: ' . $phone;
        }

        if ($whatsapp = config('support.whatsapp')) {
            $parts[] = 'WhatsApp: ' . $whatsapp;
        }

        if ($email = config('support.email')) {
            $parts[] = 'ইমেইল: ' . $email;
        }

        $parts[] = 'সময়: ' . config('support.hours');

        return 'যেকোনো সমস্যা, মতামত বা সাহায্যের জন্য লিখুন। ' . implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make()
                ->label('নতুন বার্তা পাঠান')
                ->using(fn (array $data, string $model): Model => app(FeedbackService::class)->submit(auth()->user(), $data))
                ->successNotificationTitle('বার্তা পাঠানো হয়েছে। উত্তর এলে এখানে দেখতে পাবেন।'),
        ];

        $whatsapp = Phone::normalize((string) config('support.whatsapp'));

        if (Phone::isValid($whatsapp)) {
            $actions[] = Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                ->color('success')
                ->url('https://wa.me/88' . $whatsapp)
                ->openUrlInNewTab();
        }

        if (filled(config('support.phone'))) {
            $actions[] = Action::make('call')
                ->label('কল করুন')
                ->icon(Heroicon::OutlinedPhone)
                ->color('gray')
                ->url('tel:' . preg_replace('/\D+/', '', (string) config('support.phone')));
        }

        return $actions;
    }
}
