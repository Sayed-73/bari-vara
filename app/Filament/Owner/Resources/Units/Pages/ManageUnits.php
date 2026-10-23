<?php

namespace App\Filament\Owner\Resources\Units\Pages;

use App\Filament\Owner\Resources\Units\UnitResource;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageUnits extends ManageRecords
{
    protected static string $resource = UnitResource::class;

    public function getSubheading(): ?string
    {
        $limit = auth()->user()->unitLimit();

        return 'ব্যবহৃত ইউনিট: '.Unit::count().' / '.($limit ?? 'সীমাহীন');
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $adminNumber = config('support.whatsapp', '01XXXXXXXXX');
        $latestRequest = $user?->planUpgradeRequests()->latest()->first();

        $statusAction = Action::make('plan_upgrade_status')
            ->label(match ($latestRequest?->status ?? null) {
                PlanUpgradeRequest::STATUS_PENDING => 'প্ল্যান: পেন্ডিং (অ্যাডমিন যাচাই হচ্ছে)',
                PlanUpgradeRequest::STATUS_APPROVED => 'প্ল্যান: অনুমোদিত',
                PlanUpgradeRequest::STATUS_REJECTED => 'প্ল্যান: বাতিল / পুনরায় চেষ্টা করুন',
                default => 'প্ল্যান স্ট্যাটাস',
            })
            ->icon(match ($latestRequest?->status ?? null) {
                PlanUpgradeRequest::STATUS_PENDING => 'heroicon-o-clock',
                PlanUpgradeRequest::STATUS_APPROVED => 'heroicon-o-check-circle',
                PlanUpgradeRequest::STATUS_REJECTED => 'heroicon-o-x-circle',
                default => 'heroicon-o-information-circle',
            })
            ->color(match ($latestRequest?->status ?? null) {
                PlanUpgradeRequest::STATUS_PENDING => 'warning',
                PlanUpgradeRequest::STATUS_APPROVED => 'success',
                PlanUpgradeRequest::STATUS_REJECTED => 'danger',
                default => 'gray',
            })
            ->disabled();

        $upgradeAction = Action::make('upgrade_plan')
            ->label('প্ল্যান আপগ্রেড')
            ->icon('heroicon-o-arrow-up-circle')
            ->color('primary')
            ->modalHeading('প্ল্যান আপগ্রেডের আবেদন')
            ->modalDescription('প্ল্যান বেছে নিন, টাকা পাঠান, Screenshot + Transaction ID দিন। অ্যাডমিন যাচাই করার পর প্ল্যানটি সক্রিয় হবে।')
            ->form([
                Select::make('plan_id')
                    ->label('প্ল্যান নির্বাচন')
                    ->options(fn (): array => Plan::query()->orderBy('monthly_fee')->get()->mapWithKeys(fn (Plan $plan): array => [
                        $plan->id => $plan->name.' — ৳ '.number_format((float) $plan->monthly_fee).'/মাস',
                    ])->all())
                    ->searchable()
                    ->required(),
                TextInput::make('amount')
                    ->label('টাকার পরিমাণ')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('৳')
                    ->required(),
                Select::make('method')
                    ->label('পেমেন্ট মাধ্যম')
                    ->options([
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank' => 'Bank',
                    ])
                    ->required(),
                TextInput::make('sender_number')
                    ->label('যে নম্বর থেকে পাঠানো হয়েছে')
                    ->tel()
                    ->placeholder('01XXXXXXXXX')
                    ->helperText('যদি থাকে'),
                TextInput::make('trx_id')
                    ->label('Transaction ID')
                    ->required()
                    ->maxLength(60),
                Textarea::make('note')
                    ->label('নোট (ঐচ্ছিক)')
                    ->rows(2)
                    ->maxLength(255),
                FileUpload::make('screenshot_path')
                    ->label('পেমেন্ট Screenshot')
                    ->image()
                    ->disk('public')
                    ->directory('plan-upgrades')
                    ->required()
                    ->helperText('Screenshot আপলোড করুন।'),
                TextInput::make('admin_number')
                    ->label('অ্যাডমিন পেমেন্ট নম্বর')
                    ->default($adminNumber)
                    ->disabled()
                    ->helperText('এই নম্বরে টাকা পাঠাতে হবে।'),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();

                if ($user->planUpgradeRequests()->where('status', PlanUpgradeRequest::STATUS_PENDING)->exists()) {
                    Notification::make()
                        ->title('পেন্ডিং আপগ্রেড আবেদন আছে')
                        ->body('আগের আবেদনটি অ্যাডমিন যাচাই না হওয়া পর্যন্ত নতুন আবেদন দিতে পারবেন না।')
                        ->warning()
                        ->send();

                    return;
                }

                $plan = Plan::findOrFail($data['plan_id']);

                $user->update([
                    'pending_plan_id' => $plan->id,
                    'plan_upgrade_status' => 'pending',
                ]);

                PlanUpgradeRequest::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'amount' => (float) $data['amount'],
                    'method' => $data['method'],
                    'trx_id' => strtoupper(trim((string) $data['trx_id'])),
                    'sender_number' => ! empty($data['sender_number']) ? preg_replace('/\D+/', '', (string) $data['sender_number']) : null,
                    'note' => $data['note'] ?? null,
                    'screenshot_path' => $data['screenshot_path'] ?? null,
                    'status' => PlanUpgradeRequest::STATUS_PENDING,
                ]);

                Notification::make()
                    ->title('আপগ্রেড আবেদন জমা হয়েছে')
                    ->body('টাকা দেওয়ার প্রমাণ ও Transaction ID জমা হয়েছে। অ্যাডমিন যাচাই করার পর প্ল্যানটি সক্রিয় হবে।')
                    ->success()
                    ->send();
            });

        if ($user && $user->planUpgradeRequests()->where('status', PlanUpgradeRequest::STATUS_PENDING)->exists()) {
            return [
                $statusAction,
                Action::make('upgrade_pending_info')
                    ->label('অ্যাডমিন যাচাই হচ্ছে')
                    ->color('warning')
                    ->disabled(),
            ];
        }

        if (! auth()->user()->canAddUnit()) {
            return [
                $statusAction,
                $upgradeAction,
                Action::make('limit')
                    ->label('ইউনিট লিমিট শেষ — প্ল্যান আপগ্রেড করুন')
                    ->color('gray')
                    ->disabled(),
            ];
        }

        return [
            $statusAction,
            $upgradeAction,
            CreateAction::make()->label('নতুন ইউনিট'),
        ];
    }
}
