<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])->components([
                Select::make('member_id')
                    ->live()
                    ->relationship('member', 'last_name')->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name . ' — ' . $record->council)->searchable(['first_name','middle_name','last_name'])->preload()
                    ->required(),
                Select::make('collection_cycle_id')
                    ->relationship('collectionCycle', 'name')
                    ->searchable()->preload()->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('₱')->minValue(0)
                    ->default(0),
                DatePicker::make('date_paid')->default(now()),
                TextInput::make('receipt_number')
                    ->required()
                    ->default(''),
                Textarea::make('notes')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
            ]);
    }
}
