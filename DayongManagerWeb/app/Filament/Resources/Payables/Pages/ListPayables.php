<?php

namespace App\Filament\Resources\Payables\Pages;

use App\Filament\Resources\Payables\PayableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayables extends ListRecords
{
    protected static string $resource = PayableResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->authorize(fn () => PayableResource::canCreate())
            ->mutateDataUsing(fn (array $data) => $data + ['recorded_by' => auth()->user()->name])];
    }
}
