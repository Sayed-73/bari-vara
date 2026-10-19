<?php

namespace App\Filament\Owner\Resources\Tenants;

use App\Filament\Owner\Resources\Tenants\Pages\ManageTenants;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationLabel = 'ভাড়াটিয়া';

    protected static ?string $modelLabel = 'ভাড়াটিয়া';

    protected static ?string $pluralModelLabel = 'ভাড়াটিয়াগণ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(255),
            TextInput::make('phone')
                ->label('মোবাইল')
                ->tel()
                ->required()
                ->regex('/^01[3-9]\d{8}$/')
                ->validationMessages(['regex' => 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)'])
                ->placeholder('01XXXXXXXXX'),
            TextInput::make('nid')->label('NID (ঐচ্ছিক)')->maxLength(30),
            TextInput::make('advance_amount')->label('অগ্রিম/জামানত')->numeric()->minValue(0)->default(0)->prefix('৳'),
            Textarea::make('notes')->label('নোট')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable(),
                TextColumn::make('phone')->label('মোবাইল')->searchable(),
                TextColumn::make('advance_amount')->label('অগ্রিম')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Tenant $record): bool => ! $record->leases()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTenants::route('/')];
    }
}
