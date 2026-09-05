<?php

namespace App\Filament\Resources\BankTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;

class BankTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])->components([
                DatePicker::make('transaction_date')
                    ->required()->default(now()),
                Select::make('transaction_type')->options(['Deposit'=>'Deposit','Withdrawal'=>'Withdrawal'])->required()->default('Deposit'),
                TextInput::make('amount')
                    ->required()
                    ->numeric()->prefix('₱')->minValue(0),
                TextInput::make('reference_number')
                    ->required()
                    ->default(''),
                Textarea::make('description')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                Hidden::make('recorded_by')->default(fn () => auth()->user()?->name ?? ''),
            ]);
    }
}
