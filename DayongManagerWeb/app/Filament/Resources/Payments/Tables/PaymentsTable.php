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
        return $table
            ->columns([
                TextColumn::make('member.full_name')->label('Member')->state(fn ($record) => $record->member->full_name)->searchable(['member.first_name','member.last_name']),
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
    }
}
