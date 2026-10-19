<?php

namespace App\Filament\Owner\Resources\Tenants\Pages;

use App\Filament\Owner\Resources\Tenants\TenantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTenants extends ManageRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('নতুন ভাড়াটিয়া')];
    }
}
