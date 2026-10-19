<?php

namespace App\Filament\Owner\Resources\Expenses\Pages;

use App\Filament\Owner\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('নতুন খরচ')];
    }
}
