<?php

namespace App\Filament\Owner\Resources\Feedback;

use App\Filament\Owner\Resources\Feedback\Pages\ManageFeedback;
use App\Models\FeedbackMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    protected static ?string $model = FeedbackMessage::class;

    protected static ?string $navigationLabel = 'সাহায্য ও মতামত';

    protected static ?string $modelLabel = 'বার্তা';

    protected static ?string $pluralModelLabel = 'সাহায্য ও মতামত';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 9;

    // A landlord only ever sees his own messages.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $answered = FeedbackMessage::query()
            ->where('user_id', auth()->id())
            ->where('status', FeedbackMessage::STATUS_ANSWERED)
            ->count();

        return $answered > 0 ? (string) $answered : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('কী নিয়ে লিখছেন')->options(FeedbackMessage::TYPES)->required(),
            TextInput::make('subject')->label('বিষয় (ঐচ্ছিক)')->maxLength(150),
            Textarea::make('message')->label('আপনার বার্তা')->required()->rows(5)->maxLength(2000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('কখন')->dateTime('d M, h:i A')->sortable(),
                TextColumn::make('type')->label('ধরন')->badge()
                    ->formatStateUsing(fn ($state): string => FeedbackMessage::TYPES[$state] ?? $state),
                TextColumn::make('message')->label('আপনার বার্তা')->limit(80)->wrap()
                    ->tooltip(fn (FeedbackMessage $record): string => $record->message),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => FeedbackMessage::STATUSES[$state] ?? $state)
                    ->color(fn ($state): string => [
                        FeedbackMessage::STATUS_NEW => 'warning',
                        FeedbackMessage::STATUS_ANSWERED => 'success',
                        FeedbackMessage::STATUS_RESOLVED => 'gray',
                    ][$state] ?? 'gray'),
                TextColumn::make('admin_reply')->label('উত্তর')->placeholder('—')->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('resolved')
                    ->label('সমাধান হয়েছে')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (FeedbackMessage $record): bool => $record->status === FeedbackMessage::STATUS_ANSWERED)
                    ->action(fn (FeedbackMessage $record) => $record->update(['status' => FeedbackMessage::STATUS_RESOLVED])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFeedback::route('/')];
    }
}
