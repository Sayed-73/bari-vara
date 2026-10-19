<?php

namespace App\Filament\Owner\Resources\Properties;

use App\Filament\Owner\Resources\Properties\Pages\ManageProperties;
use App\Models\Property;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationLabel = 'বাড়ি';

    protected static ?string $modelLabel = 'বাড়ি';

    protected static ?string $pluralModelLabel = 'বাড়িসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('বাড়ির নাম')->required()->maxLength(255),
            TextInput::make('address')->label('ঠিকানা')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('বাড়ির নাম')->searchable(),
                TextColumn::make('address')->label('ঠিকানা')->placeholder('—'),
                TextColumn::make('units_count')->label('ইউনিট')->counts('units'),
            ])
            ->recordActions([
                EditAction::make(),
                // A building with units cannot be deleted (it would erase leases and bills).
                DeleteAction::make()->visible(fn (Property $record): bool => ! $record->units()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProperties::route('/')];
    }
}
