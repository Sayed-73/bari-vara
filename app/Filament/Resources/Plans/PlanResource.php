<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationLabel = 'প্ল্যান (Plans)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // Plans are never deleted: landlords reference them. Edit the plan instead.
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
        return $schema->components([
            TextInput::make('name')->label('প্ল্যানের নাম')->required()->unique(ignoreRecord: true)->maxLength(100),
            TextInput::make('monthly_fee')->label('মাসিক ফি')->numeric()->minValue(0)->required()->prefix('৳'),
            TextInput::make('max_units')->label('সর্বোচ্চ ইউনিট (খালি = সীমাহীন)')->numeric()->integer()->minValue(0),
            CheckboxList::make('features')
                ->label('এই প্ল্যানের Feature (টিক = চালু)')
                ->options(config('features.catalog', []))
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('প্ল্যান')->searchable(),
                TextColumn::make('monthly_fee')->label('ফি')->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('max_units')->label('ইউনিট লিমিট')->placeholder('সীমাহীন'),
                TextColumn::make('users_count')->label('বাড়িওয়ালা')->counts('users'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
