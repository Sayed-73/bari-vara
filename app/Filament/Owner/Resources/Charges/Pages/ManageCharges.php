<?php

namespace App\Filament\Owner\Resources\Charges\Pages;

use App\Filament\Owner\Resources\Charges\ChargeResource;
use App\Models\Charge;
use App\Models\Lease;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageCharges extends ManageRecords
{
    protected static string $resource = ChargeResource::class;

    /** One tab per month that has bills (newest first) + "all months". Click a month to see only that month. */
    public function getTabs(): array
    {
        $months = Charge::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->limit(24)
            ->pluck('period')
            ->map(fn ($period) => Carbon::parse($period)->startOfMonth())
            ->unique(fn (Carbon $month) => $month->format('Y-m'))
            ->values();

        $tabs = [];

        foreach ($months as $month) {
            $unpaid = Charge::query()
                ->whereDate('period', $month->toDateString())
                ->where('status', '!=', Charge::STATUS_PAID)
                ->count();

            $tabs[$month->format('Y-m')] = Tab::make($month->format('M Y'))
                ->badge($unpaid > 0 ? $unpaid : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('period', $month->toDateString()));
        }

        $tabs['all'] = Tab::make('সব মাস');

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return array_key_first($this->getTabs());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('মাসিক রিপোর্ট')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()->hasFeature('reports'))
                ->url(fn (): string => route('report.month', [
                    'month' => preg_match('/^\d{4}-\d{2}$/', (string) $this->activeTab) ? $this->activeTab : now()->format('Y-m'),
                ]))
                ->openUrlInNewTab(),
            Action::make('addBill')
                ->label('নতুন বিল যোগ করো')
                ->modalHeading('নতুন বিল')
                ->modalDescription('মাসিক ভাড়া প্রতি মাসের ১ তারিখে নিজে থেকে তৈরি হয়। এখানে গ্যাস/পানি/বিদ্যুৎ বা বিশেষ বিল যোগ করো।')
                ->schema([
                    Select::make('lease_id')
                        ->label('ভাড়াটিয়া / ইউনিট')
                        ->options(fn (): array => Lease::activeOptions())
                        ->searchable()
                        ->required(),
                    Select::make('type')
                        ->label('বিলের ধরন')
                        ->options(fn (): array => ChargeResource::allowedTypes())
                        ->default('rent')
                        ->required(),
                    DatePicker::make('period')->label('কোন মাসের বিল')->default(now())->native(false)->required(),
                    TextInput::make('amount')->label('টাকা')->numeric()->minValue(0)->required()->prefix('৳'),
                    DatePicker::make('due_on')->label('শেষ তারিখ (খালি = চুক্তির দিন)')->native(false),
                    TextInput::make('description')->label('বিবরণ')->maxLength(255),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists($data['type'], ChargeResource::allowedTypes())) {
                        Notification::make()->title('এই ধরনের বিল আপনার প্ল্যানে নেই')->danger()->send();

                        return;
                    }

                    $month = Carbon::parse($data['period'])->startOfMonth();

                    $exists = Charge::query()
                        ->where('lease_id', $data['lease_id'])
                        ->where('type', $data['type'])
                        ->whereDate('period', $month->toDateString())
                        ->exists();

                    if ($exists) {
                        Notification::make()
                            ->title('এই মাসে এই ধরনের বিল আগেই আছে — সেটা Edit করো')
                            ->danger()
                            ->send();

                        return;
                    }

                    Charge::create($data);

                    Notification::make()->title('বিল যোগ হয়েছে')->success()->send();
                }),
        ];
    }
}
