<?php

namespace App\Filament\Owner\Resources\Leases\Pages;

use App\Filament\Owner\Resources\Leases\LeaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeases extends ManageRecords
{
    protected static string $resource = LeaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('নতুন চুক্তি')];
    }
}
