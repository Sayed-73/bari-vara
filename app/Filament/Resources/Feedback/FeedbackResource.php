<?php

namespace App\Filament\Resources\Feedback;

use App\Filament\Resources\Feedback\Pages\ManageFeedback;
use App\Models\FeedbackMessage;
use App\Services\FeedbackService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Super admin inbox: every landlord's help / feedback / bug / deletion request. */
class FeedbackResource extends Resource
{
    protected static ?string $model = FeedbackMessage::class;

    protected static ?string $navigationLabel = 'সাপোর্ট ইনবক্স';

    protected static ?string $modelLabel = 'বার্তা';

    protected static ?string $pluralModelLabel = 'সাপোর্ট বার্তা';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = FeedbackMessage::query()->where('status', FeedbackMessage::STATUS_NEW)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('কখন')->dateTime('d M, h:i A')->sortable(),
                TextColumn::make('user.name')->label('বাড়িওয়ালা')->searchable(),
                TextColumn::make('user.phone')->label('মোবাইল')->placeholder('—')->copyable(),
                TextColumn::make('type')->label('ধরন')->badge()
                    ->formatStateUsing(fn ($state): string => FeedbackMessage::TYPES[$state] ?? $state)
                    ->color(fn ($state): string => $state === 'delete_account' ? 'danger' : 'gray'),
                TextColumn::make('message')->label('বার্তা')->limit(90)->wrap(),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => FeedbackMessage::STATUSES[$state] ?? $state)
                    ->color(fn ($state): string => [
                        FeedbackMessage::STATUS_NEW => 'danger',
                        FeedbackMessage::STATUS_ANSWERED => 'warning',
                        FeedbackMessage::STATUS_RESOLVED => 'success',
                    ][$state] ?? 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(FeedbackMessage::STATUSES)
                    ->default(FeedbackMessage::STATUS_NEW),
                SelectFilter::make('type')->label('ধরন')->options(FeedbackMessage::TYPES),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('উত্তর দিন')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->modalHeading(fn (FeedbackMessage $record): string => (FeedbackMessage::TYPES[$record->type] ?? 'বার্তা') . ' — ' . ($record->user?->name ?? ''))
                    ->modalDescription(fn (FeedbackMessage $record): string => $record->message)
                    ->fillForm(fn (FeedbackMessage $record): array => ['reply' => $record->admin_reply])
                    ->schema([
                        Textarea::make('reply')->label('আপনার উত্তর')->required()->rows(4)->maxLength(2000),
                    ])
                    ->action(function (FeedbackMessage $record, array $data): void {
                        app(FeedbackService::class)->reply($record, $data['reply'], auth()->user());

                        Notification::make()->title('উত্তর পাঠানো হয়েছে')->success()->send();
                    }),
                Action::make('resolve')
                    ->label('সমাধান')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (FeedbackMessage $record): bool => $record->status !== FeedbackMessage::STATUS_RESOLVED)
                    ->requiresConfirmation()
                    ->action(fn (FeedbackMessage $record) => app(FeedbackService::class)->resolve($record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFeedback::route('/')];
    }
}
