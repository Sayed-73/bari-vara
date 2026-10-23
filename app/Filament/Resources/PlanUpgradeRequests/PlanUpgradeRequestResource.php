<?php

namespace App\Filament\Resources\PlanUpgradeRequests;

use App\Filament\Resources\PlanUpgradeRequests\Pages\ListPlanUpgradeRequests;
use App\Models\PlanUpgradeRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class PlanUpgradeRequestResource extends Resource
{
    protected static ?string $model = PlanUpgradeRequest::class;

    protected static ?string $navigationLabel = 'প্ল্যান আপগ্রেড আবেদন';

    protected static ?string $modelLabel = 'প্ল্যান আপগ্রেড আবেদন';

    protected static ?string $pluralModelLabel = 'প্ল্যান আপগ্রেড আবেদন';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = PlanUpgradeRequest::query()->where('status', PlanUpgradeRequest::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'plan']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses('transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md hover:border-amber-200 hover:bg-amber-50/40')
            ->striped()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')->label('তারিখ')->dateTime('d M, h:i A')->sortable()->toggleable(),
                TextColumn::make('user.name')->label('বাড়িওয়ালা')->searchable()->sortable()->weight('medium'),
                TextColumn::make('plan.name')->label('প্ল্যান')->searchable()->badge()->color('info'),
                TextColumn::make('amount')->label('টাকা')->formatStateUsing(fn ($state): string => '৳ '.number_format((float) $state, 2))->sortable()->weight('semibold'),
                TextColumn::make('method')->label('মাধ্যম')->badge()->color(fn (string $state): string => match ($state) {
                    'bkash' => 'pink',
                    'nagad' => 'warning',
                    'bank' => 'info',
                    default => 'gray',
                })->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('trx_id')->label('Transaction ID')->searchable()->copyable()->wrap()->color('primary'),
                TextColumn::make('sender_number')->label('নম্বর')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state): string => [
                        PlanUpgradeRequest::STATUS_PENDING => 'পেন্ডিং',
                        PlanUpgradeRequest::STATUS_APPROVED => 'অনুমোদিত',
                        PlanUpgradeRequest::STATUS_REJECTED => 'বাতিল',
                    ][$state] ?? $state)
                    ->color(fn ($state): string => [
                        PlanUpgradeRequest::STATUS_PENDING => 'warning',
                        PlanUpgradeRequest::STATUS_APPROVED => 'success',
                        PlanUpgradeRequest::STATUS_REJECTED => 'danger',
                    ][$state] ?? 'gray'),
                ImageColumn::make('screenshot_path')
                    ->label('স্ক্রিনশট')
                    ->disk('public')
                    // ->height(48)
                    // ->width(64)
                    // ->extraImgAttributes(['class' => 'rounded-lg object-cover cursor-pointer ring-1 ring-slate-200 dark:ring-slate-700'])
                    // ->defaultImageUrl(null)
                    // ->placeholder('—')
                    ->action(
                        Action::make('view_screenshot_modal')
                        ->label('View')
                            ->modalHeading('পেমেন্ট স্ক্রিনশট')
                            ->modalContent(fn (PlanUpgradeRequest $record): HtmlString => new HtmlString(
                                '<div class="flex flex-col items-center gap-4 p-4">'
                                .'<img src="'.Storage::disk('public')->url($record->screenshot_path).'" '
                                .'alt="Payment screenshot" '
                                .'class="max-h-[70vh] w-auto rounded-xl shadow-lg object-contain" />'
                                .'<a href="'.Storage::disk('public')->url($record->screenshot_path).'" '
                                .'download target="_blank" '
                                .'class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition">'
                                .'<i class="fa-solid fa-download"></i> ডাউনলোড করুন</a>'
                                .'</div>'
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('বন্ধ করুন')
                    ),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options([
                    PlanUpgradeRequest::STATUS_PENDING => 'পেন্ডিং',
                    PlanUpgradeRequest::STATUS_APPROVED => 'অনুমোদিত',
                    PlanUpgradeRequest::STATUS_REJECTED => 'বাতিল',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('অনুমোদন')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PlanUpgradeRequest $record): bool => $record->status === PlanUpgradeRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (PlanUpgradeRequest $record): void {
                        $user = $record->user;

                        if (! $user) {
                            return;
                        }

                        $user->update([
                            'plan_id' => $record->plan_id,
                            'pending_plan_id' => null,
                            'plan_upgrade_status' => 'approved',
                            'subscription_ends_at' => null,
                        ]);

                        $record->update([
                            'status' => PlanUpgradeRequest::STATUS_APPROVED,
                            'admin_id' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('প্ল্যান আপগ্রেড অনুমোদিত')
                            ->body("{$user->name} এর প্ল্যান সফলভাবে আপগ্রেড হয়েছে।")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('বাতিল')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (PlanUpgradeRequest $record): bool => $record->status === PlanUpgradeRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('review_note')->label('কারণ')->rows(3)->required(),
                    ])
                    ->action(function (PlanUpgradeRequest $record, array $data): void {
                        $user = $record->user;

                        if ($user) {
                            $user->update([
                                'pending_plan_id' => null,
                                'plan_upgrade_status' => 'rejected',
                            ]);
                        }

                        $record->update([
                            'status' => PlanUpgradeRequest::STATUS_REJECTED,
                            'admin_id' => Auth::id(),
                            'review_note' => $data['review_note'],
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('আপগ্রেড আবেদন বাতিল হয়েছে')
                            ->danger()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanUpgradeRequests::route('/'),
        ];
    }
}
