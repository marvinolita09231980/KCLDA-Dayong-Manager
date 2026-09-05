<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        $table
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
                TextColumn::make('contact_number')->visibleFrom('md')
                    ->searchable(),
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

        return \App\Services\ResponsiveTable::configure($table, ['full_name', 'member_status']);
    }
}
