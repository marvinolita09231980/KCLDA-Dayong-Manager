<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        $table
            ->striped()->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)->columns([
                TextColumn::make('member.full_name')->label('Member')->state(fn ($record) => $record->member->full_name)->searchable(['first_name', 'last_name']),
                TextColumn::make('member.council')->searchable(),
                TextColumn::make('collectionCycle.name')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('date_paid')
                    ->date()
                    ->sortable(),
                TextColumn::make('receipt_number')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return \App\Services\ResponsiveTable::configure($table, ['member.full_name', 'member.council', 'collectionCycle.name', 'amount']);
    }
}
