<?php

namespace App\Filament\Owner\Resources\Expenses;

use App\Filament\Owner\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationLabel = 'খরচ';

    protected static ?string $modelLabel = 'খরচ';

    protected static ?string $pluralModelLabel = 'খরচসমূহ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 7;

    // Admin can switch this feature on/off per plan or per landlord at any time.
    public static function canAccess(): bool
    {
        return auth()->user()?->hasFeature('expense_tracking') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('property');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('property_id')->label('বাড়ি (ঐচ্ছিক)')->relationship('property', 'name')->searchable()->preload(),
            Select::make('category')->label('খরচের ধরন')->options(Expense::CATEGORIES)->required(),
            TextInput::make('amount')->label('টাকা')->numeric()->minValue(0)->required()->prefix('৳'),
            DatePicker::make('spent_on')->label('তারিখ')->default(now())->native(false)->required(),
            TextInput::make('note')->label('বিবরণ')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('spent_on')->label('তারিখ')->date('d M Y')->sortable(),
                TextColumn::make('category')->label('ধরন')->badge()
                    ->formatStateUsing(fn ($state): string => Expense::CATEGORIES[$state] ?? $state),
                TextColumn::make('property.name')->label('বাড়ি')->placeholder('—'),
                TextColumn::make('amount')->label('টাকা')
                    ->formatStateUsing(fn ($state): string => '৳ ' . number_format((float) $state)),
                TextColumn::make('note')->label('বিবরণ')->placeholder('—')->limit(40),
            ])
            ->defaultSort('spent_on', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }
}
