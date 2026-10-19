<?php

namespace App\Filament\Owner\Resources\Properties\Pages;

use App\Filament\Owner\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProperties extends ManageRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        if (! auth()->user()->canAddProperty()) {
            return [
                Action::make('limit')
                    ->label('একাধিক বাড়ির সুবিধা আপনার প্ল্যানে নেই')
                    ->color('gray')
                    ->disabled(),
            ];
        }

        return [CreateAction::make()->label('নতুন বাড়ি')];
    }
}
