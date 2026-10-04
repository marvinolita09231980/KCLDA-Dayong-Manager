<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use App\Services\PaymentCycleColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        $table
            ->heading('Recorded payments')
            ->deferFilters(false)
            ->filtersTriggerAction(fn (\Filament\Actions\Action $action) => $action->hidden())
            ->filtersFormColumns(['default' => 1, 'md' => 3])
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)->columns([
                TextColumn::make('row_number')->label('#')->rowIndex(),
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
                TextInputColumn::make('receipt_number')
                    ->label('Receipt number')
                    ->searchable()
                    ->rules(['required', 'max:255'])
                    ->disabled(fn ($record, $livewire): bool => ! $livewire->receiptEditing || ! PaymentResource::canEdit($record)),
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
                SelectFilter::make('council')
                    ->label('Council')
                    ->options(fn () => Member::query()
                        ->whereNotNull('council')
                        ->where('council', '!=', '')
                        ->distinct()
                        ->orderBy('council')
                        ->pluck('council', 'council'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $query, string $council): Builder => $query
                            ->whereHas('member', fn (Builder $query): Builder => $query->where('council', $council)))),
                SelectFilter::make('collection_cycle_id')
                    ->label('Collection cycles')
                    ->options(fn () => PaymentCycleColumns::all()->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(filled($data['values'] ?? []),
                        fn (Builder $query) => $query->whereIn('collection_cycle_id', PaymentCycleColumns::selected(['collection_cycle_id' => $data])->flatMap(fn ($column) => $column['ids'])->all())))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Filter::make('payment_date')
                    ->label('Payment date')
                    ->schema([
                        DatePicker::make('from')->label('Paid from')->maxDate(fn (Get $get) => $get('to')),
                        DatePicker::make('to')->label('Paid through')->minDate(fn (Get $get) => $get('from')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date_paid', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date_paid', '<=', $to)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['from'] ?? null) ? 'Paid from '.$data['from'] : null,
                        filled($data['to'] ?? null) ? 'Paid through '.$data['to'] : null,
                    ])),
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
