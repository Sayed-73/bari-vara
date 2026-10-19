<?php

namespace App\Filament\Owner\Resources\Payments;

use App\Filament\Owner\Resources\Payments\Pages\ManagePayments;
use App\Models\Charge;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationLabel = 'পেমেন্ট';

    protected static ?string $modelLabel = 'পেমেন্ট';

    protected static ?string $pluralModelLabel = 'পেমেন্টসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 6;

    // Payments are recorded from the Bills page ("পেমেন্ট নিন"), not created here.
    public static function canCreate(): bool
    {
        return false;
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
                TextColumn::make('paid_at')->label('তারিখ')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('charge.lease.tenant.name')->label('ভাড়াটিয়া')->searchable(),
                TextColumn::make('charge.lease.unit.name')->label('ইউনিট'),
                TextColumn::make('charge.period')->label('কোন মাস')->date('M Y'),
                TextColumn::make('charge.type')->label('ধরন')
                    ->formatStateUsing(fn ($state): string => Charge::TYPES[$state] ?? (string) $state),
                TextColumn::make('amount')->label('টাকা')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('method')->label('মাধ্যম')->badge()
                    ->formatStateUsing(fn ($state): string => Payment::METHODS[$state] ?? $state),
                TextColumn::make('trx_id')->label('Trx ID')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                Action::make('receipt')
                    ->label('রসিদ')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (): bool => (bool) auth()->user()->hasFeature('receipt_pdf'))
                    ->url(fn (Payment $record): string => $record->receiptUrl())
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->modalDescription('ভুল এন্ট্রি মুছলে বিলের "জমা" ও অবস্থা নিজে থেকে ঠিক হয়ে যাবে।'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
