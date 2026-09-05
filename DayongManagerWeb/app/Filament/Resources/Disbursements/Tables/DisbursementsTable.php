<?php

namespace App\Filament\Resources\Disbursements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DisbursementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('disbursement_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('voucher_number')
                    ->searchable(),
                TextColumn::make('payee')
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('recorded_by')
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
