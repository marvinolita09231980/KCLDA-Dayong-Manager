<?php

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDisbursements extends ListRecords
{
    protected static string $resource = DisbursementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
