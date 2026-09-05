<?php

namespace App\Services;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResponsiveTable
{
    public static function configure(Table $table, array $primary): Table
    {
        $columns = $table->getColumns();
        foreach ($columns as $column) {
            $column->extraCellAttributes([
                'data-mobile-label' => e($column->getLabel()),
                'class' => in_array($column->getName(), $primary, true) ? 'dayong-mobile-primary' : 'dayong-mobile-secondary',
            ], merge: true);
        }

        $detailsAction = Action::make('details')->label('View details')->icon('heroicon-o-eye')
                    ->modalHeading('Record details')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(function (Model $record) use ($columns) {
                        $details = [];
                        foreach ($columns as $column) {
                            $displayColumn = clone $column;
                            $displayColumn->record($record);
                            $value = $displayColumn->getState();
                            $details[$column->getLabel()] = $value;
                        }
                        foreach (['notes', 'description', 'particulars', 'remarks'] as $field) {
                            if (array_key_exists($field, $record->getAttributes())) {
                                $details[Str::headline($field)] = $record->getAttribute($field);
                            }
                        }

                        return view('filament.record-details', compact('details'));
                    });
        $editAction = EditAction::make()->authorize(fn (Model $record, $livewire) => $livewire::getResource()::canEdit($record));
        $deleteAction = DeleteAction::make()->authorize(fn (Model $record, $livewire) => $livewire::getResource()::canDelete($record));
        $actions = in_array('full_name', $primary, true)
            ? [Action::make('recordPayment')->label('Record payment')->icon('heroicon-o-banknotes')
                ->visible(fn () => \App\Filament\Resources\Payments\PaymentResource::canCreate())
                ->url(fn (Model $record) => \App\Filament\Resources\Payments\PaymentResource::getUrl('create', ['member_id' => $record->getKey()])),
                $detailsAction, ActionGroup::make([$editAction, $deleteAction])->label('More actions')->icon('heroicon-o-ellipsis-vertical')]
            : [$detailsAction, $editAction, ActionGroup::make([$deleteAction])->label('More actions')->icon('heroicon-o-ellipsis-vertical')];

        return $table->extraAttributes(['class' => 'dayong-responsive-table'])
            ->recordUrl(null)->recordAction('details')->recordActions($actions);
    }
}
