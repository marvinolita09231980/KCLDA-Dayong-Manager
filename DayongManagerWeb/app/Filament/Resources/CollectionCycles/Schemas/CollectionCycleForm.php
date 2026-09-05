<?php

namespace App\Filament\Resources\CollectionCycles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CollectionCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')->options(['Dayong'=>'Dayong','Annual Dues'=>'Annual Dues','Registration Fee'=>'Registration Fee'])->required(),
                TextInput::make('expected_amount')
                    ->required()
                    ->numeric()->prefix('₱')->minValue(0),
                DatePicker::make('start_date'),
                DatePicker::make('due_date'),
                Toggle::make('active')
                    ->required()->default(true),
            ]);
    }
}
