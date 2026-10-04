<?php

namespace App\Filament\Resources\Disbursements\Tables;

use App\Filament\Resources\Disbursements\DisbursementResource;
use App\Models\Disbursement;
use App\Services\DayongFinancialPeriod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class DisbursementsTable
{
    public static function configure(Table $table): Table
    {
        $table
            ->striped()->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)->columns([
                TextColumn::make('disbursement_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('voucher_number')
                    ->searchable(),
                TextColumn::make('payee')
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('financial_period')
                    ->label('Period')
                    ->state(fn (Disbursement $record) => $record->closes_financial_period ? 'Closed' : null)
                    ->badge()->color('success')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->summarize(
                        Sum::make('total')
                            ->label('Total disbursements')
                            ->money('PHP'),
                    )
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
                Action::make('closeFinancialPeriod')
                    ->label('Close period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Close financial reporting period')
                    ->modalDescription('This Claims disbursement will become the final transaction in the current period. Its closing balance will become the beginning balance of the next financial report.')
                    ->visible(fn (Disbursement $record) => DisbursementResource::canViewAny() && auth()->user()->hasPermission('disbursements.edit')
                        && app(DayongFinancialPeriod::class)->canClose($record))
                    ->action(function (Disbursement $record): void {
                        abort_unless(DisbursementResource::canViewAny() && auth()->user()->hasPermission('disbursements.edit'), 403);
                        app(DayongFinancialPeriod::class)->close($record);
                        Notification::make()->title('Financial period closed')->body('The closing balance is now the beginning balance for the next report.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(fn (Disbursement $record) => DisbursementResource::canDelete($record)),
                ]),
            ]);

        return \App\Services\ResponsiveTable::configure($table, ['payee', 'category', 'amount', 'disbursement_date']);
    }
}
