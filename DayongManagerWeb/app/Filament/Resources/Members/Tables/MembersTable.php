<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CollectionCycle;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        $payableCycles = null;
        $table
            ->header(fn () => view('filament.members-unpaid-cycles', [
                'cycles' => auth()->user()?->hasPermission('collections.view') ? CollectionCycle::orderByDesc('id')->get(['id', 'name']) : collect(),
            ]))
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                if (! (auth()->user()?->hasPermission('collections.view') ?? false)) {
                    return $query;
                }
                $query->with('payments');
                $cycleIds = CollectionCycle::whereKey($livewire->unpaidCycleIds ?? [])->pluck('id');
                if ($cycleIds->isEmpty()) {
                    return $query;
                }

                $clause = ($livewire->unpaidCycleMatch ?? 'or') === 'and' ? 'where' : 'orWhere';
                return $query->where(function (Builder $query) use ($cycleIds, $clause): void {
                    foreach ($cycleIds as $cycleId) {
                        $query->{$clause}(function (Builder $members) use ($cycleId): void {
                            $members->where(fn (Builder $eligible) => $eligible
                                ->whereNull('start_cycle_id')->orWhere('start_cycle_id', '<=', $cycleId))
                                ->whereDoesntHave('payments', fn (Builder $payments) => $payments
                                    ->where('collection_cycle_id', $cycleId)->where('amount', '>', 0));
                        });
                    }
                });
            })
            ->striped()->paginationPageOptions([10, 25, 50])->defaultPaginationPageOption(10)->columns([
                TextColumn::make('full_name')->label('Member')->description(fn ($record) => $record->council)->wrap()->state(fn ($record) => $record->full_name)->searchable(['last_name','first_name','middle_name'])->sortable(['last_name','first_name']),
                TextColumn::make('birth_date')->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
                TextColumn::make('council')->visibleFrom('md')
                    ->searchable(),
                TextColumn::make('membership_type')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('sponsor_name')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('unpaid_payables')->label('Unpaid payables')
                    ->visible(fn () => auth()->user()?->hasPermission('collections.view') ?? false)
                    ->state(function ($record) use (&$payableCycles): array {
                        $payableCycles ??= CollectionCycle::orderBy('id')->get();
                        $payments = $record->payments->keyBy('collection_cycle_id');

                        return $payableCycles
                            ->filter(fn ($cycle) => (! $record->start_cycle_id || $cycle->id >= $record->start_cycle_id)
                                && (float) $cycle->expected_amount > 0
                                && (float) ($payments->get($cycle->id)?->amount ?? 0) <= 0)
                            ->map(fn ($cycle) => $cycle->name.' — ₱'.number_format((float) $cycle->expected_amount, 2))
                            ->values()->all();
                    })
                    ->listWithLineBreaks()->wrap()->placeholder('None'),
                TextColumn::make('beneficiary_name')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('beneficiary_contact')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                IconColumn::make('is_fourth_degree')->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                TextColumn::make('member_status')
                    ->badge()->color(fn (string $state) => match ($state) {'Active'=>'success','Inactive'=>'warning','Expelled'=>'danger','Deceased'=>'gray',default=>'gray'}),
                TextColumn::make('registration_date')->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
                TextColumn::make('startCycle.name')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('date_of_death')->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
                TextColumn::make('service_date')->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
                TextColumn::make('claim_received_date')->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
                TextColumn::make('claim_received_by')->toggleable(isToggledHiddenByDefault: true)
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
                SelectFilter::make('council')->options(fn () => \App\Models\Member::query()->distinct()->orderBy('council')->pluck('council','council')),
                SelectFilter::make('member_status')->options(['Active'=>'Active','Inactive'=>'Inactive','Expelled'=>'Expelled','Deceased'=>'Deceased']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return \App\Services\ResponsiveTable::configure($table, ['full_name', 'unpaid_payables', 'member_status']);
    }
}
