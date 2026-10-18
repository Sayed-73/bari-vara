<?php

namespace App\Filament\Resources\Landlords\Pages;

use App\Filament\Resources\Landlords\LandlordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandlords extends ListRecords
{
    protected static string $resource = LandlordResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
