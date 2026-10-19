<?php

namespace App\Filament\Owner\Resources\Units;

use App\Filament\Owner\Resources\Units\Pages\ManageUnits;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationLabel = 'ইউনিট (ফ্ল্যাট/দোকান)';

    protected static ?string $modelLabel = 'ইউনিট';

    protected static ?string $pluralModelLabel = 'ইউনিটসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('property');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('property_id')
                ->label('বাড়ি')
                ->relationship('property', 'name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('name')->label('ইউনিটের নাম (যেমন 2A, দোকান-৩)')->required()->maxLength(50),
            TextInput::make('base_rent')->label('সাধারণ ভাড়া')->numeric()->minValue(0)->required()->prefix('৳'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.name')->label('বাড়ি')->searchable(),
                TextColumn::make('name')->label('ইউনিট')->searchable(),
                TextColumn::make('base_rent')->label('ভাড়া')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => $state === Unit::STATUS_OCCUPIED ? 'ভাড়া দেওয়া' : 'খালি')
                    ->color(fn ($state): string => $state === Unit::STATUS_OCCUPIED ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options([
                    Unit::STATUS_VACANT => 'খালি',
                    Unit::STATUS_OCCUPIED => 'ভাড়া দেওয়া',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Unit $record): bool => ! $record->leases()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
