<?php

namespace App\Filament\Resources\CollectionCycles\Pages;

use App\Filament\Resources\CollectionCycles\CollectionCycleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCollectionCycle extends EditRecord
{
    protected static string $resource = CollectionCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
