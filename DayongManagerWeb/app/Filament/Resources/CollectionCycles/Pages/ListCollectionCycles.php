<?php

namespace App\Filament\Resources\CollectionCycles\Pages;

use App\Filament\Resources\CollectionCycles\CollectionCycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCollectionCycles extends ListRecords
{
    protected static string $resource = CollectionCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
