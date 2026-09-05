<?php

namespace App\Filament\Resources\Disbursements\Pages;

use App\Filament\Resources\Disbursements\DisbursementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDisbursement extends EditRecord
{
    protected static string $resource = DisbursementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
