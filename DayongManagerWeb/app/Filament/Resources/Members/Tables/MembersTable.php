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
        return $table
            ->columns([
                TextColumn::make('full_name')->label('Member')->state(fn ($record) => $record->full_name)->searchable(['last_name','first_name','middle_name'])->sortable(['last_name','first_name']),
                TextColumn::make('birth_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('council')
                    ->searchable(),
                TextColumn::make('membership_type')
                    ->searchable(),
                TextColumn::make('sponsor_name')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('contact_number')
                    ->searchable(),
                TextColumn::make('beneficiary_name')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('beneficiary_contact')->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                IconColumn::make('is_fourth_degree')
                    ->boolean(),
                TextColumn::make('member_status')
                    ->badge()->color(fn (string $state) => match ($state) {'Active'=>'success','Inactive'=>'warning','Expelled'=>'danger','Deceased'=>'gray',default=>'gray'}),
                TextColumn::make('registration_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('startCycle.name')
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
    }
}
