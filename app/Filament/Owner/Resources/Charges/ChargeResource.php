<?php

namespace App\Filament\Owner\Resources\Charges;

use App\Filament\Owner\Resources\Charges\Pages\ManageCharges;
use App\Models\Charge;
use BackedEnum;
use App\Services\Sms\SmsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static ?string $navigationLabel = 'মাসিক বিল';

    protected static ?string $modelLabel = 'বিল';

    protected static ?string $pluralModelLabel = 'বিলসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lease.unit.property', 'lease.tenant']);
    }

    /** Rent is always allowed; gas/water/etc. need the "utility_bills" feature. */
    public static function allowedTypes(): array
    {
        return auth()->user()?->hasFeature('utility_bills')
            ? Charge::TYPES
            : ['rent' => Charge::TYPES['rent']];
    }

    /** Edit form (the landlord can change amount/due date/description at any time). */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')->label('বিলের পরিমাণ')->numeric()->minValue(0)->required()->prefix('৳'),
            \Filament\Forms\Components\DatePicker::make('due_on')->label('শেষ তারিখ')->native(false)->required(),
            TextInput::make('description')->label('বিবরণ / নোট')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state): string => '৳ ' . number_format((float) $state);

        return $table
            ->columns([
                TextColumn::make('period')->label('মাস')->date('M Y')->sortable(),
                TextColumn::make('lease.tenant.name')->label('ভাড়াটিয়া')->searchable(),
                TextColumn::make('lease.unit.name')->label('ইউনিট'),
                TextColumn::make('type')->label('ধরন')->badge()
                    ->formatStateUsing(fn ($state): string => Charge::TYPES[$state] ?? $state),
                TextColumn::make('amount')->label('বিল')->formatStateUsing($money),
                TextColumn::make('paid_amount')->label('জমা')->formatStateUsing($money),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => [
                        Charge::STATUS_UNPAID => 'বাকি',
                        Charge::STATUS_PARTIAL => 'আংশিক',
                        Charge::STATUS_PAID => 'পরিশোধ',
                    ][$state] ?? $state)
                    ->color(fn ($state): string => [
                        Charge::STATUS_UNPAID => 'danger',
                        Charge::STATUS_PARTIAL => 'warning',
                        Charge::STATUS_PAID => 'success',
                    ][$state] ?? 'gray'),
                TextColumn::make('due_on')->label('শেষ তারিখ')->date('d M')->sortable()
                    ->color(fn (Charge $record): string => $record->status !== Charge::STATUS_PAID && $record->due_on?->isPast()
                        ? 'danger'
                        : 'gray'),
            ])
            ->defaultSort('period', 'desc')
            // Bills of the same unit/tenant sit together under one heading, so it is clear whose bill it is.
            ->groups([
                Group::make('lease_id')
                    ->label('ইউনিট')
                    ->getTitleFromRecordUsing(fn (Charge $record): string => $record->lease?->label() ?? '—')
                    ->collapsible(),
            ])
            ->defaultGroup('lease_id')
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options([
                    Charge::STATUS_UNPAID => 'বাকি',
                    Charge::STATUS_PARTIAL => 'আংশিক',
                    Charge::STATUS_PAID => 'পরিশোধ',
                ]),
                Filter::make('overdue')->label('মেয়াদ পেরিয়েছে')->toggle()
                    ->query(fn (Builder $query) => $query
                        ->where('status', '!=', Charge::STATUS_PAID)
                        ->whereDate('due_on', '<', today())),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('পেমেন্ট নিন')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Charge $record): bool => $record->status !== Charge::STATUS_PAID)
                    ->fillForm(fn (Charge $record): array => [
                        'amount' => $record->dueAmount(),
                        'method' => 'cash',
                        'paid_at' => now(),
                    ])
                    ->schema([
                        TextInput::make('amount')->label('কত টাকা পেলেন')->numeric()->minValue(1)->required()->prefix('৳'),
                        Select::make('method')->label('মাধ্যম')->options(\App\Models\Payment::METHODS)->required(),
                        TextInput::make('trx_id')->label('Transaction ID (bKash/Nagad হলে)')->maxLength(60),
                        DateTimePicker::make('paid_at')->label('তারিখ ও সময়')->required()->seconds(false),
                        TextInput::make('note')->label('নোট')->maxLength(255),
                    ])
                    ->action(function (Charge $record, array $data): void {
                        if ((float) $data['amount'] > $record->dueAmount()) {
                            Notification::make()
                                ->title('বাকির চেয়ে বেশি টাকা নেওয়া যাবে না (বাকি: ৳ ' . number_format($record->dueAmount()) . ')')
                                ->danger()
                                ->send();

                            return;
                        }

                        $payment = $record->payments()->create($data);

                        if (auth()->user()->hasFeature('sms_reminders')) {
                            app(SmsService::class)->sendReceipt($payment);
                        }

                        Notification::make()->title('পেমেন্ট জমা হয়েছে')->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('link')
                        ->label('বিল লিংক')
                        ->icon(Heroicon::OutlinedLink)
                        ->action(function (Charge $record): void {
                            Notification::make()
                                ->title('ভাড়াটিয়াকে এই লিংক পাঠাও (বিল, পেমেন্ট ও রসিদ এখানে)')
                                ->body($record->payUrl())
                                ->persistent()
                                ->send();
                        }),
                    Action::make('whatsapp')
                        ->label('WhatsApp-এ পাঠাও')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->visible(fn (Charge $record): bool => $record->status !== Charge::STATUS_PAID)
                        ->url(fn (Charge $record): string => $record->whatsappUrl())
                        ->openUrlInNewTab(),
                    Action::make('sms')
                        ->label('SMS রিমাইন্ডার')
                        ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->visible(fn (Charge $record): bool => $record->status !== Charge::STATUS_PAID
                            && (bool) auth()->user()->hasFeature('sms_reminders'))
                        ->requiresConfirmation()
                        ->modalDescription('ভাড়াটিয়ার কাছে এখনই বকেয়ার SMS যাবে (১টি SMS ক্রেডিট কাটবে)।')
                        ->action(function (Charge $record): void {
                            $log = app(SmsService::class)->sendReminder($record, 'manual');

                            $messages = [
                                'sent' => ['SMS পাঠানো হয়েছে', 'success'],
                                'no_credit' => ['SMS ক্রেডিট শেষ — অ্যাডমিনের কাছ থেকে ক্রেডিট নিন', 'danger'],
                                'invalid_number' => ['ভাড়াটিয়ার মোবাইল নম্বর সঠিক নয়', 'danger'],
                                'failed' => ['SMS পাঠানো যায়নি, পরে আবার চেষ্টা করুন', 'danger'],
                            ];

                            [$title, $color] = $messages[$log?->status] ?? ['SMS পাঠানো যায়নি', 'danger'];

                            Notification::make()->title($title)->color($color)->send();
                        }),
                ])->label('আরও'),
                EditAction::make(),
                // A bill with payments cannot be deleted; delete the payment first.
                DeleteAction::make()->visible(fn (Charge $record): bool => ! $record->payments()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCharges::route('/')];
    }
}
