<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    public array $unpaidCycleIds = [];

    public string $unpaidCycleMatch = 'or';

    public function updatedUnpaidCycleMatch(): void
    {
        $this->updatedUnpaidCycleIds();
    }

    public function updatedUnpaidCycleIds(): void
    {
        $this->resetPage();
        $this->deselectAllTableRecords();
    }

    public function clearUnpaidCycles(): void
    {
        $this->unpaidCycleIds = [];
        $this->updatedUnpaidCycleIds();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
