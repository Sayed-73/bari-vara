<?php

namespace App\Filament\Resources\Landlords;

use App\Filament\Resources\Landlords\Pages\CreateLandlord;
use App\Filament\Resources\Landlords\Pages\EditLandlord;
use App\Filament\Resources\Landlords\Pages\ListLandlords;
use App\Models\User;
use App\Support\LandlordStats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LandlordResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'বাড়িওয়ালা (Landlords)';

    protected static ?string $modelLabel = 'Landlord';

    protected static ?string $pluralModelLabel = 'Landlords';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_admin', false);
    }

    // Landlords are never hard-deleted (it would erase all their data). Switch them OFF instead.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $featureSelects = collect(config('features.catalog', []))
            ->map(fn (string $label, string $key) => Select::make("feature_overrides.{$key}")
                ->label($label)
                ->options(['on' => 'চালু (On)', 'off' => 'বন্ধ (Off)'])
                ->placeholder('প্ল্যান অনুযায়ী'))
            ->values()
            ->all();

        return $schema->components([
            Section::make('বাড়িওয়ালার তথ্য')
                ->schema([
                    TextInput::make('name')->label('নাম')->required()->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                    TextInput::make('phone')
                        ->label('মোবাইল (login-এও চলবে)')
                        ->tel()
                        ->required()
                        ->regex('/^01[3-9]\d{8}$/')
                        ->validationMessages(['regex' => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)'])
                        ->placeholder('01XXXXXXXXX')
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->label('পাসওয়ার্ড')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText('Edit করার সময় খালি রাখলে পাসওয়ার্ড বদলাবে না।'),
                ])
                ->columns(2),

            Section::make('Subscription (যেকোনো সময় বদলানো যাবে)')
                ->schema([
                    Toggle::make('is_active')->label('অ্যাকাউন্ট চালু (OFF করলে login বন্ধ)')->default(true),
                    Select::make('plan_id')->label('প্ল্যান')->relationship('plan', 'name')->preload()->searchable(),
                    TextInput::make('custom_fee')
                        ->label('বিশেষ মাসিক ফি (খালি = প্ল্যানের ফি)')
                        ->numeric()->minValue(0)->prefix('৳'),
                    TextInput::make('custom_unit_limit')
                        ->label('বিশেষ ইউনিট লিমিট (খালি = প্ল্যানের লিমিট)')
                        ->numeric()->integer()->minValue(0),
                    TextInput::make('sms_credits')
                        ->label('SMS ক্রেডিট (প্রতি SMS-এ ১টি কাটে)')
                        ->numeric()->integer()->minValue(0)->default(0),
                    DateTimePicker::make('subscription_ends_at')
                        ->label('সাবস্ক্রিপশন শেষ হবে (খালি = সীমাহীন)')
                        ->seconds(false),
                ])
                ->columns(2),

            Section::make('Feature On/Off (শুধু এই বাড়িওয়ালার জন্য)')
                ->description('"প্ল্যান অনুযায়ী" রাখলে প্ল্যানের সেটিং চলবে। On/Off দিলে সেটাই প্রাধান্য পাবে।')
                ->schema($featureSelects)
                ->columns(2)
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable()->sortable(),
                TextColumn::make('phone')->label('মোবাইল')->searchable()->placeholder('—'),
                TextColumn::make('plan.name')->label('প্ল্যান')->placeholder('—'),
                TextColumn::make('monthly_fee')
                    ->label('মাসিক ফি')
                    ->state(fn (User $record): float => $record->monthlyFee())
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('summary')
                    ->label('এই মাসের হিসাব')
                    ->state(function (User $record): string {
                        $s = LandlordStats::for($record);

                        return "ইউনিট {$s['occupied']}/{$s['units']} · বিল ৳" . number_format($s['billed'])
                            . ' · আদায় ৳' . number_format($s['collected'])
                            . ' · বকেয়া ৳' . number_format($s['due']);
                    })
                    ->wrap(),
                TextColumn::make('sms_credits')->label('SMS'),
                ToggleColumn::make('is_active')->label('চালু'),
                TextColumn::make('subscription_ends_at')
                    ->label('মেয়াদ')
                    ->dateTime('d M Y')
                    ->placeholder('সীমাহীন')
                    ->color(fn (User $record): string => $record->subscription_ends_at?->isPast() ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('চালু/বন্ধ'),
            ])
            ->recordActions([
                Action::make('view_as')
                    ->label('ড্যাশবোর্ড দেখো')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->url(fn (User $record): string => URL::temporarySignedRoute(
                        'impersonate.start',
                        now()->addMinutes(30),
                        ['user' => $record->id],
                    )),
                ActionGroup::make([
                Action::make('extend')
                    ->label('+১ মাস')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $base = $record->subscription_ends_at?->isFuture()
                            ? $record->subscription_ends_at->copy()
                            : now();

                        $record->update(['subscription_ends_at' => $base->addMonthNoOverflow()]);
                    }),
                Action::make('reduce')
                    ->label('−১ মাস')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->subscription_ends_at !== null)
                    ->action(function (User $record): void {
                        $record->update([
                            'subscription_ends_at' => $record->subscription_ends_at->copy()->subMonthNoOverflow(),
                        ]);
                    }),
                Action::make('reset_password')
                    ->label('পাসওয়ার্ড রিসেট')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('একটা নতুন অস্থায়ী পাসওয়ার্ড তৈরি হবে এবং শুধু একবার দেখানো হবে। বাড়িওয়ালাকে জানিয়ে দাও, সে প্রোফাইল থেকে বদলে নেবে।')
                    ->action(function (User $record): void {
                        $password = Str::password(10, symbols: false);

                        $record->forceFill(['password' => Hash::make($password)])->save();

                        Notification::make()
                            ->title('নতুন অস্থায়ী পাসওয়ার্ড')
                            ->body("{$record->name}: {$password}  (এখনই কপি করো, আর দেখা যাবে না)")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                ]),
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandlords::route('/'),
            'create' => CreateLandlord::route('/create'),
            'edit' => EditLandlord::route('/{record}/edit'),
        ];
    }
}
