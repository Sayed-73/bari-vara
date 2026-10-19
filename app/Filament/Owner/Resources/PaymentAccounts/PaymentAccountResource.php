<?php

namespace App\Filament\Owner\Resources\PaymentAccounts;

use App\Filament\Owner\Resources\PaymentAccounts\Pages\ManagePaymentAccounts;
use App\Models\Charge;
use App\Models\PaymentAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentAccountResource extends Resource
{
    protected static ?string $model = PaymentAccount::class;

    protected static ?string $navigationLabel = 'পেমেন্ট সেটিংস';

    protected static ?string $modelLabel = 'পেমেন্ট সেটিংস';

    protected static ?string $pluralModelLabel = 'পেমেন্ট সেটিংস';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        $online = fn (): bool => (bool) auth()->user()?->hasFeature('bkash_collection');

        return $schema->components([
            Textarea::make('manual_instructions')
                ->label('ভাড়াটিয়ারা কীভাবে পেমেন্ট দেবে (bKash/Nagad নম্বর, ব্যাংক অ্যাকাউন্ট)')
                ->helperText('এটা ভাড়াটিয়ার বিল-লিংকে "কীভাবে পেমেন্ট করবেন" নামে দেখাবে। যেমন: bKash (Personal): 01XXXXXXXXX')
                ->rows(4)
                ->maxLength(1000),
            TextInput::make('store_id')
                ->label('SSLCommerz Store ID')
                ->maxLength(100)
                ->visible($online),
            TextInput::make('store_password')
                ->label('SSLCommerz Store Password')
                ->password()
                ->revealable()
                ->maxLength(100)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Edit করার সময় খালি রাখলে আগেরটাই থাকবে। পাসওয়ার্ড এনক্রিপ্ট হয়ে সেভ হয়।')
                ->visible($online),
            Toggle::make('is_sandbox')
                ->label('টেস্ট (Sandbox) মোড — লাইভে যাওয়ার সময় বন্ধ করুন')
                ->default(true)
                ->visible($online),
            Toggle::make('is_enabled')
                ->label('অনলাইন পেমেন্ট চালু')
                ->default(false)
                ->visible($online),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')->label('গেটওয়ে')->formatStateUsing(fn (): string => 'SSLCommerz (bKash/Nagad/কার্ড/ব্যাংক)'),
                IconColumn::make('is_enabled')->label('চালু')->boolean(),
                IconColumn::make('is_sandbox')->label('টেস্ট মোড')->boolean(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('ভাড়াটিয়া যা দেখবে')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (): bool => Charge::query()->exists())
                    ->url(fn (): ?string => Charge::query()->latest('id')->first()?->payUrl())
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentAccounts::route('/')];
    }
}
