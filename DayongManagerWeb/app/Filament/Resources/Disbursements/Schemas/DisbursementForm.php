<?php

namespace App\Filament\Resources\Disbursements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;

class DisbursementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('disbursement_date')
                    ->required()->default(now()),
                TextInput::make('voucher_number')
                    ->required()
                    ->default(''),
                TextInput::make('payee')
                    ->required()
                    ->default(''),
                TextInput::make('category')
                    ->required()
                    ->default('Other Expense'),
                Textarea::make('particulars')
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()->prefix('₱')->minValue(0),
                Hidden::make('recorded_by')->default(fn () => auth()->user()?->name ?? ''),
            ]);
    }
}
