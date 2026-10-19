<?php

namespace App\Filament\Owner\Resources\PaymentClaims;

use App\Filament\Owner\Resources\PaymentClaims\Pages\ManagePaymentClaims;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Services\Payments\PaymentClaimService;
use App\Services\Payments\PaymentException;
use App\Services\Sms\SmsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PaymentClaimResource extends Resource
{
    protected static ?string $model = PaymentClaim::class;

    protected static ?string $navigationLabel = 'পেমেন্ট দাবি (যাচাই)';

    protected static ?string $modelLabel = 'পেমেন্ট দাবি';

    protected static ?string $pluralModelLabel = 'পেমেন্ট দাবিসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 6;

    // Claims are created by tenants from the bill page, never here.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = PaymentClaim::query()->where('status', PaymentClaim::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['charge.lease.unit', 'charge.lease.tenant']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('কখন')->dateTime('d M, h:i A')->sortable(),
                TextColumn::make('charge.lease.tenant.name')->label('ভাড়াটিয়া')->searchable(),
                TextColumn::make('charge.lease.unit.name')->label('ইউনিট'),
                TextColumn::make('charge.period')->label('মাস')->date('M Y'),
                TextColumn::make('amount')->label('টাকা')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('method')->label('মাধ্যম')->badge()
                    ->formatStateUsing(fn ($state): string => Payment::METHODS[$state] ?? $state),
                TextColumn::make('trx_id')->label('Transaction ID')->copyable()->searchable(),
                TextColumn::make('sender_number')->label('যে নম্বর থেকে')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => [
                        PaymentClaim::STATUS_PENDING => 'যাচাই বাকি',
                        PaymentClaim::STATUS_CONFIRMED => 'গ্রহণ করা হয়েছে',
                        PaymentClaim::STATUS_REJECTED => 'বাতিল',
                    ][$state] ?? $state)
                    ->color(fn ($state): string => [
                        PaymentClaim::STATUS_PENDING => 'warning',
                        PaymentClaim::STATUS_CONFIRMED => 'success',
                        PaymentClaim::STATUS_REJECTED => 'danger',
                    ][$state] ?? 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->default(PaymentClaim::STATUS_PENDING)->options([
                    PaymentClaim::STATUS_PENDING => 'যাচাই বাকি',
                    PaymentClaim::STATUS_CONFIRMED => 'গ্রহণ করা হয়েছে',
                    PaymentClaim::STATUS_REJECTED => 'বাতিল',
                ]),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('যাচাই করে গ্রহণ')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PaymentClaim $record): bool => $record->status === PaymentClaim::STATUS_PENDING)
                    ->modalDescription('আপনার bKash/Nagad/ব্যাংকে টাকা ঢুকেছে এবং Transaction ID মিলেছে তো? তাহলেই গ্রহণ করুন। টাকার পরিমাণ ঠিক করা যায়।')
                    ->fillForm(fn (PaymentClaim $record): array => ['amount' => (float) $record->amount])
                    ->schema([
                        TextInput::make('amount')->label('গ্রহণ করা টাকা')->numeric()->minValue(1)->required()->prefix('৳'),
                    ])
                    ->action(function (PaymentClaim $record, array $data): void {
                        try {
                            $payment = app(PaymentClaimService::class)->confirm($record, (float) $data['amount']);
                        } catch (PaymentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                            return;
                        }

                        app(SmsService::class)->sendReceipt($payment);

                        Notification::make()->title('পেমেন্ট গ্রহণ করা হয়েছে')->success()->send();
                    }),
                Action::make('reject')
                    ->label('বাতিল')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (PaymentClaim $record): bool => $record->status === PaymentClaim::STATUS_PENDING)
                    ->schema([
                        Textarea::make('reason')->label('কারণ (ভাড়াটিয়া দেখতে পাবে)')->rows(2)->maxLength(255),
                    ])
                    ->action(function (PaymentClaim $record, array $data): void {
                        try {
                            app(PaymentClaimService::class)->reject($record, $data['reason'] ?? null);
                        } catch (PaymentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('দাবি বাতিল করা হয়েছে')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentClaims::route('/')];
    }
}
