<?php

namespace App\Filament\Owner\Resources\Leases;

use App\Filament\Owner\Resources\Leases\Pages\ManageLeases;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;

    protected static ?string $navigationLabel = 'ভাড়ার চুক্তি';

    protected static ?string $modelLabel = 'চুক্তি';

    protected static ?string $pluralModelLabel = 'চুক্তিসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['unit.property', 'tenant']);
    }

    public static function form(Schema $schema): Schema
    {
        $unitLabel = fn ($value): ?string => ($unit = Unit::with('property')->find($value))
            ? "{$unit->property->name} – {$unit->name}"
            : null;

        return $schema->components([
            Select::make('unit_id')
                ->label('ইউনিট (শুধু খালি ইউনিট দেখাচ্ছে)')
                ->searchable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->live()
                ->options(fn (): array => Unit::query()
                    ->with('property')
                    ->where('status', Unit::STATUS_VACANT)
                    ->get()
                    ->mapWithKeys(fn (Unit $unit) => [$unit->id => "{$unit->property->name} – {$unit->name}"])
                    ->all())
                ->getOptionLabelUsing($unitLabel)
                ->afterStateUpdated(function ($state, Set $set): void {
                    $unit = $state ? Unit::find($state) : null;

                    if ($unit) {
                        $set('monthly_rent', $unit->base_rent);
                    }
                }),
            Select::make('tenant_id')
                ->label('ভাড়াটিয়া')
                ->searchable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->options(fn (): array => Tenant::query()->orderBy('name')->get()
                    ->mapWithKeys(fn (Tenant $tenant) => [$tenant->id => "{$tenant->name} ({$tenant->phone})"])
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => ($tenant = Tenant::find($value))
                    ? "{$tenant->name} ({$tenant->phone})"
                    : null),
            DatePicker::make('starts_on')->label('শুরুর তারিখ')->required()->default(now())->native(false),
            DatePicker::make('ends_on')->label('শেষের তারিখ (খালি = চলমান)')->native(false),
            TextInput::make('monthly_rent')
                ->label('মাসিক ভাড়া (যেকোনো সময় বদলানো যাবে — পরের মাস থেকে কার্যকর)')
                ->numeric()->minValue(0)->required()->prefix('৳'),
            TextInput::make('due_day')
                ->label('প্রতি মাসের কত তারিখে ভাড়া দিতে হবে')
                ->numeric()->integer()->minValue(1)->maxValue(28)->default(5)->required(),
            Toggle::make('is_active')->label('চুক্তি চালু')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit.property.name')->label('বাড়ি'),
                TextColumn::make('unit.name')->label('ইউনিট'),
                TextColumn::make('tenant.name')->label('ভাড়াটিয়া')->searchable(),
                TextColumn::make('monthly_rent')->label('ভাড়া')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('due_day')->label('তারিখ'),
                IconColumn::make('is_active')->label('চালু')->boolean(),
                TextColumn::make('starts_on')->label('শুরু')->date('d M Y'),
            ])
            ->defaultSort('is_active', 'desc')
            ->recordActions([
                Action::make('end')
                    ->label('চুক্তি শেষ করো')
                    ->icon(Heroicon::OutlinedStopCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('ইউনিটটি আবার "খালি" হয়ে যাবে। আগের বিল ও পেমেন্ট থাকবে।')
                    ->visible(fn (Lease $record): bool => $record->is_active)
                    ->action(fn (Lease $record) => $record->update([
                        'is_active' => false,
                        'ends_on' => $record->ends_on ?? today(),
                    ])),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Lease $record): bool => ! $record->charges()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeases::route('/')];
    }
}
