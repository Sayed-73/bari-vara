<?php

namespace App\Filament\Owner\Resources\Units\Pages;

use App\Filament\Owner\Resources\Units\UnitResource;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUnits extends ManageRecords
{
    protected static string $resource = UnitResource::class;

    public function getSubheading(): ?string
    {
        $limit = auth()->user()->unitLimit();

        return 'ব্যবহৃত ইউনিট: ' . Unit::count() . ' / ' . ($limit ?? 'সীমাহীন');
    }

    protected function getHeaderActions(): array
    {
        if (! auth()->user()->canAddUnit()) {
            return [
                Action::make('limit')
                    ->label('ইউনিট লিমিট শেষ — প্ল্যান আপগ্রেড করুন')
                    ->color('gray')
                    ->disabled(),
            ];
        }

        return [CreateAction::make()->label('নতুন ইউনিট')];
    }
}
